<?php

namespace App\Services;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Events\MilestoneChanged;
use App\Exceptions\ApiException;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MilestoneService
{
    public const MAX_PER_PROJECT = 10;

    public function __construct(private ProjectActivityLogger $activities, private ProgressCalculator $progress) {}

    /** @return Collection<int, ProjectMilestone> */
    public function list(Project $project): Collection
    {
        return $project->milestones()->withCount('updates')->get();
    }

    /** Syarat umum mengelola milestone: workspace aktif dan proyek sedang berjalan (R1.2). */
    public function assertManageable(Project $project): void
    {
        if (! $project->isPaymentConfirmed()) {
            throw new ApiException('Workspace aktif setelah pembayaran terkonfirmasi.', 409);
        }

        if (! in_array($project->status, [ProjectStatus::Approved, ProjectStatus::Process], true)) {
            throw new ApiException('Milestone hanya dapat dikelola pada proyek yang sedang berjalan.', 409);
        }
    }

    /** @param  array{title: string, description?: ?string, due_date?: ?string}  $data */
    public function create(Project $project, array $data, User $actor): ProjectMilestone
    {
        $this->assertManageable($project);

        $milestone = DB::transaction(function () use ($project, $data, $actor) {
            Project::whereKey($project->id)->lockForUpdate()->first();

            $count = $project->milestones()->count();
            if ($count >= self::MAX_PER_PROJECT) {
                throw ValidationException::withMessages(['title' => 'Maksimal '.self::MAX_PER_PROJECT.' milestone per proyek.']);
            }

            $milestone = new ProjectMilestone($data);
            $milestone->project_id = $project->id;
            $milestone->position = $count + 1;
            $milestone->status = MilestoneStatus::Planned;
            $milestone->percent = 0;
            $milestone->save();

            $this->activities->log($project, 'milestone.created', ['milestone_id' => $milestone->id, 'title' => $milestone->title], $actor);
            $this->progress->recalculate($project);

            return $milestone;
        });

        MilestoneChanged::dispatch($project);

        return $milestone->loadCount('updates');
    }

    /** @param  array{title?: string, description?: ?string, due_date?: ?string}  $data */
    public function update(ProjectMilestone $milestone, array $data, User $actor): ProjectMilestone
    {
        $project = $milestone->project;
        $this->assertManageable($project);

        if ($milestone->isWorkedOn()) {
            $locked = collect(['title', 'description'])->first(fn ($f) => array_key_exists($f, $data) && ($data[$f] ?? null) !== $milestone->{$f});
            if ($locked) {
                throw new ApiException('Judul dan deskripsi tidak dapat diubah setelah milestone dikerjakan.', 409);
            }
            $data = array_intersect_key($data, ['due_date' => true]);
        }

        DB::transaction(function () use ($milestone, $data, $project, $actor) {
            $milestone->fill($data);

            if ($milestone->isDirty()) {
                $milestone->save();
                $this->activities->log($project, 'milestone.updated', ['milestone_id' => $milestone->id, 'title' => $milestone->title], $actor);
            }
        });

        MilestoneChanged::dispatch($project);

        return $milestone->refresh()->loadCount('updates');
    }

    public function delete(ProjectMilestone $milestone, User $actor): void
    {
        $project = $milestone->project;
        $this->assertManageable($project);

        if ($milestone->isWorkedOn()) {
            throw new ApiException('Milestone yang sudah dikerjakan tidak dapat dihapus.', 409);
        }

        DB::transaction(function () use ($milestone, $project, $actor) {
            $milestone->delete();

            // Rapatkan nomor urut (1..n tanpa celah).
            $project->milestones()->get()->each(fn (ProjectMilestone $m, int $i) => $m->position === $i + 1 ?: $m->forceFill(['position' => $i + 1])->save());

            $this->activities->log($project, 'milestone.deleted', ['milestone_id' => $milestone->id, 'title' => $milestone->title], $actor);
            $this->progress->recalculate($project);
        });

        MilestoneChanged::dispatch($project);
    }

    /**
     * Susun ulang milestone `planned`. `$ids` harus memuat SEMUA id milestone `planned` proyek ini;
     * milestone yang sudah dikerjakan tetap pada posisinya, yang planned mengisi posisi kosong sesuai urutan baru.
     *
     * @param  list<int>  $ids
     */
    public function reorder(Project $project, array $ids, User $actor): void
    {
        $this->assertManageable($project);

        DB::transaction(function () use ($project, $ids, $actor) {
            $planned = $project->milestones()->where('status', MilestoneStatus::Planned->value)->get();

            $expected = $planned->pluck('id')->sort()->values()->all();
            $given = collect($ids)->map(fn ($i) => (int) $i)->sort()->values()->all();

            if ($expected !== $given) {
                throw ValidationException::withMessages(['ids' => 'Daftar urutan harus memuat semua milestone yang masih direncanakan.']);
            }

            $slots = $planned->pluck('position')->sort()->values();
            $changed = false;

            foreach ($ids as $i => $id) {
                $m = $planned->firstWhere('id', (int) $id);
                if ($m->position !== $slots[$i]) {
                    $m->forceFill(['position' => $slots[$i]])->save();
                    $changed = true;
                }
            }

            if ($changed) {
                $this->activities->log($project, 'milestone.reordered', [], $actor);
            }
        });

        MilestoneChanged::dispatch($project);
    }
}
