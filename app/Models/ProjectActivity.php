<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris riwayat aktivitas proyek (append-only). Dibuat HANYA lewat App\Services\ProjectActivityLogger.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $actor_id
 * @property string $type
 * @property array<string, mixed> $meta
 * @property Carbon $created_at
 */
class ProjectActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
