<?php

namespace App\Events;

use App\Http\Resources\UpdateResource;
use App\Models\ProjectUpdate;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Developer mengirim progres atau hasil. */
class ProjectUpdateSubmitted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ProjectUpdate $update) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('project.'.$this->update->project_id)];
    }

    public function broadcastAs(): string
    {
        return 'update.created';
    }

    public function broadcastWith(): array
    {
        $this->update->loadMissing(['author', 'files']);

        return (new UpdateResource($this->update))->resolve();
    }
}
