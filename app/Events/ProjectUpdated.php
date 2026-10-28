<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Status atau harga orderan berubah. Dikirim ke pemilik orderan dan ke panel admin.
 */
class ProjectUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Project $project) {}

    public function broadcastOn(): array
    {
        return array_values(array_filter([
            new PrivateChannel('App.Models.User.'.$this->project->user_id),
            $this->project->developer_id ? new PrivateChannel('App.Models.User.'.$this->project->developer_id) : null,
            new PrivateChannel('admin.orders'),
        ]));
    }

    public function broadcastAs(): string
    {
        return 'project.updated';
    }

    /** Hanya field yang dibutuhkan UI (tanpa data pribadi tambahan). */
    public function broadcastWith(): array
    {
        $p = $this->project;

        return [
            'id' => $p->id,
            'project_title' => $p->project_title,
            'status' => $p->status->value,
            'reject_reason' => $p->reject_reason,
            'plan_price_range' => $p->plan_price_range,
            'developer_id' => $p->developer_id,
            'progress_percent' => $p->progress_percent,
            'invoice_number' => $p->invoice_number,
            'invoice_issued_at' => $p->invoice_issued_at,
            'updated_at' => $p->updated_at,
        ];
    }
}
