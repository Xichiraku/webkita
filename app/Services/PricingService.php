<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\PricingPlan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PricingService
{
    /** min wajib >= 0; max boleh null (tanpa batas atas), jika diisi harus > min. */
    public function assertPriceRange(float|int $min, float|int|null $max): void
    {
        if ($max !== null && $max <= $min) {
            throw new ApiException('max_price harus lebih besar dari min_price', 400);
        }
    }

    /** @return Collection<int, PricingPlan> */
    public function getAllActive(): Collection
    {
        return $this->query()->where('is_active', true)->get();
    }

    /** @return Collection<int, PricingPlan> */
    public function getAll(): Collection
    {
        return $this->query()->get();
    }

    public function getByTier(string $tier): PricingPlan
    {
        return $this->query()->where('tier', $tier)->first()
            ?? throw new ApiException('Plan not found', 404);
    }

    public function create(array $data): PricingPlan
    {
        $this->assertPriceRange($data['min_price'], $data['max_price'] ?? null);

        return DB::transaction(function () use ($data) {
            $plan = PricingPlan::create([
                ...collect($data)->except(['features', 'not_included'])->all(),
                'max_price' => $data['max_price'] ?? null,
            ]);

            $this->syncLines($plan, 'features', $data['features'] ?? []);
            $this->syncLines($plan, 'notIncluded', $data['not_included'] ?? []);

            return $this->fresh($plan);
        });
    }

    public function update(PricingPlan $plan, array $data): PricingPlan
    {
        if (array_key_exists('min_price', $data) || array_key_exists('max_price', $data)) {
            $this->assertPriceRange(
                $data['min_price'] ?? $plan->min_price,
                array_key_exists('max_price', $data) ? $data['max_price'] : $plan->max_price,
            );
        }

        return DB::transaction(function () use ($plan, $data) {
            $plan->update(collect($data)->except(['features', 'not_included'])->all());

            if (array_key_exists('features', $data)) {
                $this->syncLines($plan, 'features', $data['features'] ?? []);
            }
            if (array_key_exists('not_included', $data)) {
                $this->syncLines($plan, 'notIncluded', $data['not_included'] ?? []);
            }

            return $this->fresh($plan);
        });
    }

    public function delete(PricingPlan $plan): void
    {
        $plan->delete(); // fitur ikut terhapus lewat FK cascade
    }

    public function toggleActive(PricingPlan $plan): PricingPlan
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return $this->fresh($plan);
    }

    private function query()
    {
        return PricingPlan::with(['features', 'notIncluded'])->orderBy('sort_order');
    }

    private function fresh(PricingPlan $plan): PricingPlan
    {
        return $plan->refresh()->load(['features', 'notIncluded']);
    }

    /** Ganti seluruh baris fitur: hapus lama, sisipkan baru (urutan = indeks bila sort_order kosong). */
    private function syncLines(PricingPlan $plan, string $relation, array $lines): void
    {
        $plan->{$relation}()->delete();

        foreach (array_values($lines) as $i => $line) {
            $plan->{$relation}()->create([
                'feature' => $line['feature'],
                'sort_order' => $line['sort_order'] ?? $i,
            ]);
        }
    }
}
