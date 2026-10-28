<?php

namespace App\Events;

use App\Http\Resources\MilestoneResource;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Milestone dibuat/diubah/dihapus/diurutkan, atau statusnya berubah. Mengirim seluruh daftar
 * milestone terbaru supaya UI cukup mengganti daftarnya (menghindari selisih urutan/penomoran).
 */
class MilestoneChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Project $project) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('project.'.$this->project->id)];
    }

    public function broadcastAs(): string
    {
        return 'milestone.changed';
    }

    public function broadcastWith(): array
    {
        $project = $this->project->fresh();

        return [
            'milestones' => $project->milestones->map(fn (ProjectMilestone $m) => (new MilestoneResource($m))->resolve())->all(),
            'project' => ['status' => $project->status->value, 'progress_percent' => $project->progress_percent],
        ];
    }
}
