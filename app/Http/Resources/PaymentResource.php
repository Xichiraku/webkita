<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'user_id' => $this->user_id,
            'order_id' => $this->order_id,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'snap_token' => $this->snap_token,
            'snap_url' => $this->snap_url,
            'paid_at' => $this->paid_at,
            'transaction_id' => $this->transaction_id,
            'payment_type' => $this->payment_type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
