<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $update_id
 * @property int|null $message_id
 * @property int|null $uploaded_by
 * @property string $file_name
 * @property int $file_size
 * @property string $file_type
 * @property string $storage_path
 * @property Carbon $created_at
 */
#[Fillable(['project_id', 'update_id', 'message_id', 'file_name', 'file_size', 'file_type', 'storage_path', 'uploaded_by'])]
class ProjectAttachment extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
