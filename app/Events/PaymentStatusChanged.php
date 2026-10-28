<?php

namespace App\Events;

use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Transaksi dibuat atau statusnya berubah (mis. dari webhook Midtrans).
 */
class PaymentStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->payment->user_id),
            new PrivateChannel('admin.orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payment.changed';
    }

    public function broadcastWith(): array
    {
        // snap_token/snap_url tidak ikut dikirim.
        return collect((new PaymentResource($this->payment))->resolve())->except(['snap_token', 'snap_url'])->all();
    }
}
