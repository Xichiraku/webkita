<?php

namespace App\Events;

use App\Http\Resources\CommentResource;
use App\Models\ProjectComment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Komentar baru. `can_delete` dihitung ulang di klien dari `author.id` dan `created_at`. */
class ProjectCommentPosted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ProjectComment $comment) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('project.'.$this->comment->project_id)];
    }

    public function broadcastAs(): string
    {
        return 'comment.created';
    }

    public function broadcastWith(): array
    {
        $this->comment->loadMissing('author');

        return [...(new CommentResource($this->comment))->resolve(request()), 'can_delete' => false];
    }
}
