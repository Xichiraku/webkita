<?php

namespace App\Services;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\ReviewStatus;
use App\Enums\RevisionStatus;
use App\Events\MilestoneChanged;
use App\Events\ProjectUpdated;
use App\Events\ProjectUpdateSubmitted;
use App\Exceptions\ApiException;
use App\Mail\ResultSubmittedMail;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectMilestone;
use App\Models\ProjectRevision;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Developer mengirim progres atau hasil pada sebuah milestone (Fitur 2).
 * Seluruh perubahan (update, berkas, milestone, proyek, log) berjalan atomik.
 */
class MilestoneUpdateService
{
    public const DISK = 'local';

    public function __construct(
        private ProjectActivityLogger $activities,
        private ProgressCalculator $progress,
        private ProjectService $projects,
    ) {}

    /**
     * @param  array{type: string, note: string, percent?: ?int, title?: ?string, links?: list<string>}  $data
     * @param  list<UploadedFile>  $files
     */
    public function submit(User $developer, ProjectMilestone $milestone, array $data, array $files = []): ProjectUpdate
    {
        $project = $milestone->project;

        if (! $project->isPaymentConfirmed()) {
            throw new ApiException('Workspace aktif setelah pembayaran terkonfirmasi.', 409);
        }

        $stored = [];
        $becameProcess = false;

        try {
            $update = DB::transaction(function () use ($developer, $milestone, $project, $data, $files, &$stored, &$becameProcess) {
                // Kunci baris proyek dan milestone agar dua kiriman bersamaan diperiksa berurutan.
                $lockedProject = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
                $locked = ProjectMilestone::whereKey($milestone->id)->lockForUpdate()->firstOrFail();

                $this->assertCanReceive($lockedProject, $locked);

                $isResult = $data['type'] === ProjectUpdate::TYPE_RESULT;
                $percent = $isResult ? 100 : (int) $data['percent'];

                if (! $isResult && $percent < $locked->percent) {
                    throw ValidationException::withMessages(['percent' => "Persentase tidak boleh lebih kecil dari progres terakhir ({$locked->percent}%)."]);
                }

                $update = new ProjectUpdate;
                $update->project_id = $project->id;
                $update->milestone_id = $locked->id;
                $update->user_id = $developer->id;
                $update->type = $data['type'];
                $update->title = $isResult ? $data['title'] : null;
                $update->note = $data['note'];
                $update->percent = $percent;
                $update->links = array_values(array_filter($data['links'] ?? []));
                $update->review_status = $isResult ? ReviewStatus::Pending : null;
                $update->save();

                foreach ($files as $file) {
                    $mime = $file->getMimeType() ?: 'application/octet-stream';
                    $path = $file->store("attachments/{$project->id}/updates/{$update->id}", self::DISK);
                    $stored[] = $path;

                    $attachment = new ProjectAttachment;
                    $attachment->project_id = $project->id;
                    $attachment->update_id = $update->id;
                    $attachment->file_name = basename($file->getClientOriginalName());
                    $attachment->file_size = (int) $file->getSize();
                    $attachment->file_type = $mime;
                    $attachment->storage_path = $path;
                    $attachment->uploaded_by = $developer->id;
                    $attachment->save();
                }

                // Hasil pengganti menutup revisi yang sedang terbuka pada milestone ini (R3.13).
                if ($isResult) {
                    ProjectRevision::where('milestone_id', $locked->id)->where('status', RevisionStatus::Open->value)
                        ->update(['status' => RevisionStatus::Resolved->value, 'resolved_by_update_id' => $update->id, 'resolved_at' => now()]);
                }

                $locked->forceFill([
                    'status' => $isResult ? MilestoneStatus::Submitted : MilestoneStatus::InProgress,
                    'percent' => $percent,
                ])->save();

                $this->progress->recalculate($lockedProject);

                $base = ['milestone_id' => $locked->id, 'milestone_title' => $locked->title, 'update_id' => $update->id];
                $isResult
                    ? $this->activities->log($lockedProject, 'update.result', $base + ['title' => $update->title], $developer)
                    : $this->activities->log($lockedProject, 'update.progress', $base + ['percent' => $percent], $developer);

                // Update pertama memindahkan proyek approved -> process (R2.13).
                if ($lockedProject->status === ProjectStatus::Approved) {
                    $becameProcess = $this->projects->transitionBySystem($lockedProject, ProjectStatus::Process);
                }

                return $update;
            });
        } catch (Throwable $e) {
            // Berkas yang telanjur ditulis dibersihkan bila transaksi gagal (R2.15).
            if ($stored) {
                Storage::disk(self::DISK)->delete($stored);
            }

            throw $e;
        }

        $update->load(['author', 'files']);

        ProjectUpdateSubmitted::dispatch($update);
        MilestoneChanged::dispatch($project);

        if (! $becameProcess) {
            ProjectUpdated::dispatch($project->refresh()); // persentase berubah
        }

        if ($update->type === ProjectUpdate::TYPE_RESULT) {
            Mail::to($project->user->email)->queue(new ResultSubmittedMail($project->user, $project, $milestone->refresh(), $update));
        }

        return $update;
    }

    /** Aturan R2.1–R2.3 terhadap data yang sudah dikunci. */
    private function assertCanReceive(Project $project, ProjectMilestone $milestone): void
    {
        if (! in_array($project->status, [ProjectStatus::Approved, ProjectStatus::Process], true)) {
            throw new ApiException('Proyek tidak dapat menerima update pada status ini.', 409);
        }

        match ($milestone->status) {
            MilestoneStatus::Approved => throw new ApiException('Milestone ini sudah disetujui.', 409),
            MilestoneStatus::Submitted => throw new ApiException('Milestone sedang menunggu review klien.', 409),
            default => null,
        };
    }
}
