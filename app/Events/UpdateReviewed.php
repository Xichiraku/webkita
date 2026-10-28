<?php

namespace App\Events;

use App\Http\Resources\MilestoneResource;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectUpdate;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Klien menyetujui atau meminta revisi atas sebuah hasil. */
class UpdateReviewed implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ProjectUpdate $update) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('project.'.$this->update->project_id)];
    }

    public function broadcastAs(): string
    {
        return 'update.reviewed';
    }

    public function broadcastWith(): array
    {
        $update = $this->update->fresh();
        $project = Project::findOrFail($update->project_id);
        $milestone = ProjectMilestone::withTrashed()->findOrFail($update->milestone_id);

        return [
            'update_id' => $update->id,
            'review_status' => $update->review_status?->value,
            'milestone' => (new MilestoneResource($milestone))->resolve(),
            'project' => [
                'status' => $project->status->value,
                'progress_percent' => $project->progress_percent,
                'revisions_used' => $project->revisions_used,
                'revision_limit' => $project->revision_limit,
            ],
        ];
    }
}
