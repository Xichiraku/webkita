<?php

namespace App\Http\Resources;

use App\Models\ProjectComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectComment */
class CommentResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $deleted = $this->deleted_at !== null;
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'update_id' => $this->update_id,
            // isi komentar terhapus tidak pernah dikirim (R3.5)
            'body' => $deleted ? null : $this->body,
            'deleted' => $deleted,
            'author' => ['id' => $this->author->id, 'name' => $this->author->name, 'role' => $this->author->role->value],
            'can_delete' => $viewer !== null && $this->resource->canBeDeletedBy($viewer),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
