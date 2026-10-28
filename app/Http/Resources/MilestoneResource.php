<?php

namespace App\Http\Resources;

use App\Models\ProjectMilestone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectMilestone */
class MilestoneResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'position' => $this->position,
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'percent' => $this->percent,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'updates_count' => $this->updates_count ?? $this->updates()->count(),
            'worked_on' => $this->isWorkedOn(),
        ];
    }
}
