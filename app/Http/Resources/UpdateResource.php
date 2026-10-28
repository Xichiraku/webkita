<?php

namespace App\Http\Resources;

use App\Models\ProjectUpdate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectUpdate */
class UpdateResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'milestone_id' => $this->milestone_id,
            'type' => $this->type,
            'title' => $this->title,
            'note' => $this->note,
            'percent' => $this->percent,
            'links' => $this->links,
            'review_status' => $this->review_status?->value,
            'author' => ['id' => $this->author->id, 'name' => $this->author->name],
            'files' => $this->files->map(fn ($f) => [
                'id' => $f->id, 'file_name' => $f->file_name, 'file_size' => $f->file_size, 'file_type' => $f->file_type,
            ])->values(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
