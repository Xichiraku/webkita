<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Events\NewProjectCreated;
use App\Events\ProjectUpdated;
use App\Exceptions\ApiException;
use App\Mail\DeveloperAssignedMail;
use App\Models\Project;
use App\Models\User;
use App\Support\Price;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ProjectService
{
    public const MAX_REJECT_REASON = 500;

    public function __construct(private ProjectActivityLogger $activities) {}

    public function create(User $user, array $data): Project
    {
        $project = new Project([
            ...$data,
            'user_id' => $user->id,
            'plan_title' => $data['plan_title'] ?? '',
            'price_range' => $data['price_range'] ?? '',
            'additional_notes' => $data['additional_notes'] ?? '',
        ]);
        $project->status = ProjectStatus::Pending;
        $project->revision_limit = config('workspace.revision_limit');

        DB::transaction(function () use ($project, $user) {
            $project->save();
            $this->activities->log($project, 'project.created', ['plan' => $project->plan_title ?: null], $user);
        });
        $project->refresh();

        NewProjectCreated::dispatch($project);

        return $project;
    }

    /** @return Collection<int, Project> */
    public function forUser(int $userId): Collection
    {
        return Project::where('user_id', $userId)->latest()->get();
    }

    /** @return Collection<int, Project> */
    public function all(): Collection
    {
        return Project::latest()->get();
    }

    public function updateStatus(Project $project, ProjectStatus $status, ?string $reason = null, ?User $actor = null): Project
    {
        $reason = trim((string) $reason);

        if ($project->status === $status) {
            return $project; // sudah di status itu, tidak ada yang berubah
        }

        if ($project->status->canBecomeBySystem($status)) {
            throw new ApiException('Status ini diatur otomatis oleh sistem.', 409);
        }

        if (! $project->status->canBecome($status)) {
            throw new ApiException(
                $project->status === ProjectStatus::Rejected
                    ? 'Orderan yang ditolak tidak dapat diubah lagi'
                    : "Status tidak dapat diubah dari \"{$project->status->value}\" ke \"{$status->value}\"",
                409,
            );
        }

        $update = ['status' => $status->value, 'updated_at' => now()];
        if ($status === ProjectStatus::Rejected) {
            $update['reject_reason'] = $reason !== '' ? $reason : null;
        }

        $from = $project->status;

        DB::transaction(function () use ($project, $update, $from, $status, $actor) {
            // Syarat status lama mencegah dua admin saling menimpa status yang sama.
            $affected = Project::whereKey($project->id)->where('status', $from->value)->update($update);

            if ($affected === 0) {
                throw new ApiException('Status orderan baru saja diubah oleh pengguna lain, muat ulang data', 409);
            }

            $this->activities->log($project, 'status.changed', [
                'from' => $from->value, 'to' => $status->value, 'reason' => $update['reject_reason'] ?? null,
            ], $actor);
        });

        $project->refresh();
        ProjectUpdated::dispatch($project);

        return $project;
    }

    /**
     * Admin menetapkan harga final. Hanya boleh sekali (harga yang sudah ditagihkan tidak boleh berubah).
     * Invoice (nomor + tanggal terbit) diterbitkan saat harga ditetapkan.
     */
    public function setPrice(Project $project, mixed $rawPrice, ?User $actor = null): Project
    {
        $price = Price::parse($rawPrice);

        if (! $price) {
            throw new ApiException('Harga tidak valid', 400);
        }
        if ($price < Price::MIN_PROJECT || $price > Price::MAX_PROJECT) {
            throw new ApiException('Harga di luar batas yang diizinkan', 400);
        }
        if ($project->status === ProjectStatus::Rejected) {
            throw new ApiException('Harga tidak dapat ditetapkan untuk orderan yang ditolak', 409);
        }
        if ($project->plan_price_range) {
            throw new ApiException('Harga sudah ditetapkan dan tidak dapat diubah', 403);
        }

        $now = now();

        $invoiceNumber = sprintf('INV-%04d-%d', $project->id, $now->year);

        DB::transaction(function () use ($project, $price, $now, $invoiceNumber, $actor) {
            // Atomik: gagal bila admin lain sudah mengisi lebih dulu.
            $affected = Project::whereKey($project->id)
                ->where(fn ($q) => $q->whereNull('plan_price_range')->orWhere('plan_price_range', ''))
                ->update([
                    'plan_price_range' => (string) $price,
                    'invoice_number' => $invoiceNumber,
                    'invoice_issued_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($affected === 0) {
                throw new ApiException('Harga sudah ditetapkan dan tidak dapat diubah', 403);
            }

            $this->activities->log($project, 'price.set', ['amount' => $price, 'invoice_number' => $invoiceNumber], $actor);
        });

        $project->refresh();
        ProjectUpdated::dispatch($project);

        return $project;
    }

    /**
     * Tugaskan (atau lepas, bila null) developer. Developer = user aktif ber-role developer atau admin
     * (admin boleh menugaskan diri sendiri). Hanya pada proyek yang sudah disetujui dan belum selesai.
     */
    public function assignDeveloper(Project $project, ?int $developerId, ?User $actor = null): Project
    {
        if (! in_array($project->status, [ProjectStatus::Approved, ProjectStatus::Process], true)) {
            throw new ApiException('Developer hanya dapat ditugaskan pada proyek yang sudah disetujui.', 409);
        }

        $developer = null;
        if ($developerId !== null) {
            $developer = User::whereKey($developerId)
                ->whereIn('role', [Role::Developer->value, Role::Admin->value])
                ->where('is_aktif', 'Y')
                ->first();

            if (! $developer) {
                throw new ApiException('Developer tidak valid', 422);
            }
        }

        if ($project->developer_id === $developer?->id) {
            return $project; // tidak ada perubahan
        }

        $previousName = $project->developer_id ? User::withTrashed()->whereKey($project->developer_id)->first()?->name : null;

        DB::transaction(function () use ($project, $developer, $previousName, $actor) {
            $project->forceFill([
                'developer_id' => $developer?->id,
                'assigned_at' => $developer ? now() : null,
            ])->save();

            if ($developer) {
                $this->activities->log($project, 'developer.assigned', [
                    'developer_id' => $developer->id, 'developer_name' => $developer->name, 'previous_name' => $previousName,
                ], $actor);
            } else {
                $this->activities->log($project, 'developer.unassigned', ['developer_name' => $previousName ?? 'Developer'], $actor);
            }
        });
        $project->refresh();

        if ($developer) {
            Mail::to($developer->email)->queue(new DeveloperAssignedMail($developer, $project));
        }

        ProjectUpdated::dispatch($project);

        return $project;
    }

    /**
     * Perpindahan status yang dilakukan sistem (bukan admin): hanya yang tercantum di
     * ProjectStatus::systemNext(). Atomik terhadap status asal; kembalikan false bila sudah berpindah.
     */
    public function transitionBySystem(Project $project, ProjectStatus $to): bool
    {
        if (! $project->status->canBecomeBySystem($to)) {
            throw new ApiException("Perpindahan status \"{$project->status->value}\" ke \"{$to->value}\" tidak diizinkan.", 409);
        }

        $changes = ['status' => $to->value, 'updated_at' => now()];
        if ($to === ProjectStatus::Done) {
            $changes += ['completed_at' => now(), 'progress_percent' => 100];
        }

        $from = $project->status;

        $moved = DB::transaction(function () use ($project, $changes, $from, $to) {
            $affected = Project::whereKey($project->id)->where('status', $from->value)->update($changes);

            if ($affected === 0) {
                return false;
            }

            $this->activities->logSystem($project, 'status.changed', ['from' => $from->value, 'to' => $to->value, 'reason' => null]);

            return true;
        });

        if (! $moved) {
            return false;
        }

        ProjectUpdated::dispatch($project->refresh());

        return true;
    }

    public function delete(Project $project): void
    {
        $project->delete(); // soft delete
    }
}
