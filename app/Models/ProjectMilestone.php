<?php

namespace App\Models;

use App\Enums\MilestoneStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tahapan pekerjaan sebuah proyek; unit pelaporan progres dan hasil.
 *
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property string|null $description
 * @property int $position
 * @property Carbon|null $due_date
 * @property MilestoneStatus $status
 * @property int $percent
 * @property Carbon|null $approved_at
 */
class ProjectMilestone extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'description', 'due_date'];

    protected function casts(): array
    {
        return [
            'status' => MilestoneStatus::class,
            'due_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<ProjectUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class, 'milestone_id');
    }

    /** Sudah dikerjakan = punya update atau statusnya bukan `planned`. */
    public function isWorkedOn(): bool
    {
        return $this->status !== MilestoneStatus::Planned || $this->updates()->exists();
    }
}
