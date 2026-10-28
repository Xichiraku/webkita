<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Komentar pada proyek (umum atau tertaut ke satu update). Tidak dapat diedit (K11);
 * hapus = soft delete oleh pemilik dalam batas waktu.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $update_id
 * @property int $user_id
 * @property string $body
 * @property Carbon $created_at
 * @property Carbon|null $deleted_at
 * @property int|null $deleted_by
 */
class ProjectComment extends Model
{
    use SoftDeletes;

    public const UPDATED_AT = null;

    /** Batas waktu pemilik boleh menghapus komentarnya (menit). */
    public const DELETE_WINDOW_MINUTES = 15;

    protected $fillable = [];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ProjectUpdate, $this> */
    public function projectUpdate(): BelongsTo
    {
        return $this->belongsTo(ProjectUpdate::class, 'update_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function canBeDeletedBy(User $user): bool
    {
        return $this->deleted_at === null
            && $this->user_id === $user->id
            && $this->created_at->diffInMinutes(now(), true) < self::DELETE_WINDOW_MINUTES;
    }
}
