<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu kiriman developer pada sebuah milestone (progres atau hasil). Tidak dapat diubah/dihapus (K11).
 *
 * @property int $id
 * @property int $project_id
 * @property int $milestone_id
 * @property int $user_id
 * @property string $type
 * @property string|null $title
 * @property string $note
 * @property int $percent
 * @property list<string> $links
 * @property ReviewStatus|null $review_status
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property Carbon $created_at
 */
class ProjectUpdate extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_PROGRESS = 'progress';

    public const TYPE_RESULT = 'result';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'links' => 'array',
            'review_status' => ReviewStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ProjectMilestone, $this> */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** @return HasMany<ProjectAttachment, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class, 'update_id');
    }
}
