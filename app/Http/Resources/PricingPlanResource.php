<?php

namespace App\Http\Resources;

use App\Models\PricingPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PricingPlan */
class PricingPlanResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $lines = fn ($rel) => $this->{$rel}->map(fn ($l) => [
            'id' => $l->id, 'feature' => $l->feature, 'sort_order' => $l->sort_order,
        ])->values();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'tier' => $this->tier,
            'min_price' => (float) $this->min_price,
            'max_price' => $this->max_price === null ? null : (float) $this->max_price,
            'price_range' => $this->price_range,
            'timeline' => $this->timeline,
            'badge' => $this->badge,
            'description' => $this->description,
            'upgrade_note' => $this->upgrade_note,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'features' => $lines('features'),
            'not_included' => $lines('notIncluded'),
        ];
    }
}
