<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu pesan chat klien–developer. Urutan mengikuti `id` (R5.8); tidak dapat diedit (K11).
 *
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property string|null $body
 * @property Carbon $created_at
 * @property Carbon|null $deleted_at
 * @property int|null $deleted_by
 */
class ChatMessage extends Model
{
    use SoftDeletes;

    public const UPDATED_AT = null;

    /** Batas waktu pengirim boleh menghapus pesannya (menit). */
    public const DELETE_WINDOW_MINUTES = 5;

    protected $fillable = [];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** @return HasMany<ProjectAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class, 'message_id');
    }

    public function canBeDeletedBy(User $user): bool
    {
        return $this->deleted_at === null
            && $this->user_id === $user->id
            && $this->created_at->diffInMinutes(now(), true) < self::DELETE_WINDOW_MINUTES;
    }
}
