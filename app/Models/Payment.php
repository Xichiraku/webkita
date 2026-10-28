<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Carbon\Carbon;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property string $order_id
 * @property string $amount
 * @property PaymentStatus $status
 * @property string|null $snap_token
 * @property string|null $snap_url
 * @property Carbon|null $paid_at
 * @property string|null $transaction_id
 * @property string|null $payment_type
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'project_id', 'user_id', 'order_id', 'amount', 'status', 'snap_token', 'snap_url',
    'paid_at', 'transaction_id', 'payment_type',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
