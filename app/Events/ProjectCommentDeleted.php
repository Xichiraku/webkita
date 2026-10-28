<?php

namespace App\Events;

use App\Models\ProjectComment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Komentar dihapus pemiliknya; hanya id yang dikirim (isi tidak pernah disiarkan). */
class ProjectCommentDeleted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ProjectComment $comment) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('project.'.$this->comment->project_id)];
    }

    public function broadcastAs(): string
    {
        return 'comment.deleted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->comment->id];
    }
}
