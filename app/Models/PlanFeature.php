<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $plan_id
 * @property string $feature
 * @property int $sort_order
 */
#[Fillable(['plan_id', 'feature', 'sort_order'])]
class PlanFeature extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @return BelongsTo<PricingPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class, 'plan_id');
    }
}
