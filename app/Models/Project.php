<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use Carbon\Carbon;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $developer_id
 * @property Carbon|null $assigned_at
 * @property int $progress_percent
 * @property Carbon|null $completed_at
 * @property int|null $revision_limit
 * @property int $revisions_used
 * @property string $plan_title
 * @property string $price_range
 * @property string|null $plan_price_range
 * @property string $project_title
 * @property string $category
 * @property string $description
 * @property string $skills
 * @property string $contact_name
 * @property string $contact_phone
 * @property string|null $additional_notes
 * @property ProjectStatus $status
 * @property string|null $reject_reason
 * @property string|null $invoice_number
 * @property Carbon|null $invoice_issued_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'user_id', 'plan_title', 'price_range', 'plan_price_range', 'project_title', 'category',
    'description', 'skills', 'contact_name', 'contact_phone', 'additional_notes',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'invoice_issued_at' => 'datetime',
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function developer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'developer_id');
    }

    /**
     * Pembayaran terkonfirmasi (full payment): syarat aktifnya Project Workspace.
     * Modul Workspace hanya membaca status ini; bila definisinya berubah (mis. DP persentase),
     * cukup ubah metode ini.
     */
    public function isPaymentConfirmed(): bool
    {
        return $this->payments()->where('status', PaymentStatus::Paid->value)->exists();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<ProjectMilestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('position');
    }

    /** @return HasMany<ProjectUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class);
    }

    /** @return HasMany<ProjectComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ProjectComment::class);
    }

    /** @return HasMany<ProjectRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ProjectRevision::class)->orderBy('number');
    }

    /** @return HasMany<ChatMessage, $this> */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /** Kuota revisi proyek masih tersedia (kosong = tanpa batas). */
    public function hasRevisionQuota(): bool
    {
        return $this->revision_limit === null || $this->revisions_used < $this->revision_limit;
    }

    /** @return HasMany<ProjectActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class);
    }

    /** @return HasMany<ProjectAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class);
    }
}
