<?php

namespace App\Models;

use App\Enums\RevisionStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permintaan revisi atas satu hasil. Nomor berurut per proyek; `open` sampai developer
 * mengirim hasil pengganti pada milestone yang sama.
 *
 * @property int $id
 * @property int $project_id
 * @property int $milestone_id
 * @property int $update_id
 * @property int $number
 * @property string $reason
 * @property int $requested_by
 * @property RevisionStatus $status
 * @property int|null $resolved_by_update_id
 * @property Carbon $created_at
 * @property Carbon|null $resolved_at
 */
class ProjectRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status' => RevisionStatus::class,
            'resolved_at' => 'datetime',
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
}
