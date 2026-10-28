<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $title
 * @property string $tier
 * @property string $min_price
 * @property string|null $max_price
 * @property string $price_range
 * @property string|null $timeline
 * @property string|null $badge
 * @property string|null $description
 * @property string|null $upgrade_note
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable([
    'title', 'tier', 'min_price', 'max_price', 'price_range', 'timeline', 'badge',
    'description', 'upgrade_note', 'is_active', 'sort_order',
])]
class PricingPlan extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'min_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<PlanFeature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'plan_id')->orderBy('sort_order');
    }

    /** @return HasMany<PlanNotIncluded, $this> */
    public function notIncluded(): HasMany
    {
        return $this->hasMany(PlanNotIncluded::class, 'plan_id')->orderBy('sort_order');
    }
}
