<?php

namespace App\Events;

use App\Models\ProjectActivity;
use App\Support\ActivityPresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Aktivitas baru pada proyek. Dikirim ke channel per proyek (klien pemilik dan developer ditugaskan).
 */
class ProjectActivityCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ProjectActivity $activity) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('project.'.$this->activity->project_id)];
    }

    public function broadcastAs(): string
    {
        return 'activity.created';
    }

    public function broadcastWith(): array
    {
        return ActivityPresenter::toArray($this->activity);
    }
}
