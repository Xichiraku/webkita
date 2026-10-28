<?php

namespace App\Http\Resources;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChatMessage */
class ChatMessageResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $deleted = $this->deleted_at !== null;
        $viewer = $request->user();

        return [
            'id' => $this->id,
            // isi dan lampiran pesan terhapus tidak pernah dikirim (R5.7)
            'body' => $deleted ? null : $this->body,
            'deleted' => $deleted,
            'author' => ['id' => $this->author->id, 'name' => $this->author->name, 'role' => $this->author->role->value],
            'attachments' => $deleted ? [] : $this->attachments->map(fn ($a) => [
                'id' => $a->id, 'file_name' => $a->file_name, 'file_size' => $a->file_size, 'file_type' => $a->file_type,
            ])->values(),
            'can_delete' => $viewer !== null && $this->resource->canBeDeletedBy($viewer),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
