<?php

namespace App\Services;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\ReviewStatus;
use App\Enums\RevisionStatus;
use App\Events\MilestoneChanged;
use App\Events\ProjectCommentPosted;
use App\Events\ProjectUpdated;
use App\Events\UpdateReviewed;
use App\Exceptions\ApiException;
use App\Mail\MilestoneApprovedMail;
use App\Mail\ProjectCompletedMail;
use App\Mail\RevisionRequestedMail;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectRevision;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Klien meninjau hasil: menyetujui (milestone final, proyek selesai bila yang terakhir)
 * atau meminta revisi (memakai kuota proyek). Juga pengaturan kuota revisi (Fitur 3 dan 4).
 */
class ReviewService
{
    public function __construct(
        private ProjectActivityLogger $activities,
        private ProgressCalculator $progress,
        private ProjectService $projects,
        private CommentService $comments,
    ) {}

    public function approve(User $client, ProjectUpdate $update): ProjectUpdate
    {
        $completed = false;

        [$project, $milestone] = DB::transaction(function () use ($client, $update, &$completed) {
            [$project, $milestone, $locked] = $this->lockPending($update);

            $locked->forceFill(['review_status' => ReviewStatus::Approved, 'reviewed_at' => now(), 'reviewed_by' => $client->id])->save();
            $milestone->forceFill(['status' => MilestoneStatus::Approved, 'percent' => 100, 'approved_at' => now()])->save();
            $this->progress->recalculate($project);

            $this->activities->log($project, 'review.approved', [
                'milestone_id' => $milestone->id, 'milestone_title' => $milestone->title, 'update_id' => $locked->id,
            ], $client);

            // Milestone terakhir disetujui -> proyek selesai, atomik dengan persetujuan (R4.4).
            if ($project->status === ProjectStatus::Process && $this->allApproved($project)) {
                $completed = $this->projects->transitionBySystem($project, ProjectStatus::Done);
            }

            return [$project, $milestone];
        });

        $update->refresh();
        UpdateReviewed::dispatch($update);
        MilestoneChanged::dispatch($project);
        ProjectUpdated::dispatch($project->refresh());

        $developer = $project->developer;

        if ($developer) {
            Mail::to($developer->email)->queue(new MilestoneApprovedMail($developer, $project, $milestone));
        }

        if ($completed) {
            Mail::to($project->user->email)->queue(new ProjectCompletedMail($project->user, $project, route('orderan.show', $project).'#milestone'));
            if ($developer) {
                Mail::to($developer->email)->queue(new ProjectCompletedMail($developer, $project, route('developer.projects.show', $project).'#milestone'));
            }
        }

        return $update;
    }

    public function requestRevision(User $client, ProjectUpdate $update, string $reason): ProjectRevision
    {
        [$project, $revision, $comment] = DB::transaction(function () use ($client, $update, $reason) {
            [$project, $milestone, $locked] = $this->lockPending($update);

            if (! $project->hasRevisionQuota()) {
                throw new ApiException("Kuota revisi proyek ({$project->revision_limit}) sudah habis. Setujui hasil atau sepakati tambahan kuota dengan developer.", 409);
            }

            $project->revisions_used++;
            Project::whereKey($project->id)->update(['revisions_used' => $project->revisions_used]);

            $revision = new ProjectRevision;
            $revision->project_id = $project->id;
            $revision->milestone_id = $milestone->id;
            $revision->update_id = $locked->id;
            $revision->number = $project->revisions_used;
            $revision->reason = $reason;
            $revision->requested_by = $client->id;
            $revision->status = RevisionStatus::Open;
            $revision->save();

            $locked->forceFill(['review_status' => ReviewStatus::RevisionRequested, 'reviewed_at' => now(), 'reviewed_by' => $client->id])->save();
            $milestone->forceFill(['status' => MilestoneStatus::Revision, 'percent' => 90])->save();
            $this->progress->recalculate($project);

            $this->activities->log($project, 'review.revision_requested', [
                'milestone_id' => $milestone->id, 'milestone_title' => $milestone->title, 'update_id' => $locked->id,
                'revision_number' => $revision->number,
            ], $client);

            // Alasan revisi tampil juga sebagai komentar pada hasil itu (R3.12).
            $comment = $this->comments->store($project, $client, $reason, $locked->id);

            return [$project, $revision->setRelation('milestone', $milestone), $comment];
        });

        $update->refresh();
        UpdateReviewed::dispatch($update);
        MilestoneChanged::dispatch($project);
        ProjectCommentPosted::dispatch($comment);
        ProjectUpdated::dispatch($project->refresh());

        if ($project->developer) {
            Mail::to($project->developer->email)->queue(new RevisionRequestedMail($project->developer, $project, $revision));
        }

        return $revision;
    }

    /** Kuota revisi disepakati dengan klien; diubah oleh developer proyek (R3.14). */
    public function setRevisionLimit(User $actor, Project $project, ?int $limit): Project
    {
        DB::transaction(function () use ($actor, $project, $limit) {
            $locked = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();

            if ($limit !== null && $limit < $locked->revisions_used) {
                throw ValidationException::withMessages(['limit' => "Kuota tidak boleh lebih kecil dari revisi yang sudah dipakai ({$locked->revisions_used})."]);
            }

            if ($locked->revision_limit === $limit) {
                return;
            }

            $from = $locked->revision_limit;
            $locked->forceFill(['revision_limit' => $limit])->save();
            $this->activities->log($locked, 'revision.limit_changed', ['from' => $from, 'to' => $limit], $actor);
        });

        ProjectUpdated::dispatch($project->refresh());

        return $project;
    }

    /**
     * Kunci proyek, milestone, dan hasil; pastikan hasil itu memang menunggu review (R3.8, R4.6).
     *
     * @return array{Project, ProjectMilestone, ProjectUpdate}
     */
    private function lockPending(ProjectUpdate $update): array
    {
        $project = Project::whereKey($update->project_id)->lockForUpdate()->firstOrFail();
        $milestone = ProjectMilestone::whereKey($update->milestone_id)->lockForUpdate()->firstOrFail();
        $locked = ProjectUpdate::whereKey($update->id)->lockForUpdate()->firstOrFail();

        if ($locked->type !== ProjectUpdate::TYPE_RESULT
            || $locked->review_status !== ReviewStatus::Pending
            || $milestone->status !== MilestoneStatus::Submitted) {
            throw new ApiException('Tidak ada hasil yang sedang menunggu review.', 409);
        }

        return [$project, $milestone, $locked];
    }

    private function allApproved(Project $project): bool
    {
        $statuses = $project->milestones()->pluck('status');

        return $statuses->isNotEmpty() && $statuses->every(fn ($s) => $s === MilestoneStatus::Approved);
    }
}
