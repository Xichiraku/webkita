<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsWithJson;
use App\Http\Requests\PricingPlanRequest;
use App\Http\Resources\PricingPlanResource;
use App\Models\PricingPlan;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;

class PricingController extends Controller
{
    use RespondsWithJson;

    public function __construct(private PricingService $pricing) {}

    // ── Publik ──
    public function publicIndex(): JsonResponse
    {
        return $this->ok(PricingPlanResource::collection($this->pricing->getAllActive()));
    }

    public function publicTier(string $tier): JsonResponse
    {
        return $this->ok(new PricingPlanResource($this->pricing->getByTier($tier)));
    }

    // ── Admin/developer ──
    public function index(): JsonResponse
    {
        return $this->ok(PricingPlanResource::collection($this->pricing->getAll()));
    }

    public function show(PricingPlan $plan): JsonResponse
    {
        return $this->ok(new PricingPlanResource($plan->load(['features', 'notIncluded'])));
    }

    public function store(PricingPlanRequest $request): JsonResponse
    {
        return $this->ok(new PricingPlanResource($this->pricing->create($request->validated())), status: 201);
    }

    public function update(PricingPlanRequest $request, PricingPlan $plan): JsonResponse
    {
        return $this->ok(new PricingPlanResource($this->pricing->update($plan, $request->validated())));
    }

    public function destroy(PricingPlan $plan): JsonResponse
    {
        $this->pricing->delete($plan);

        return $this->ok(['message' => 'Plan deleted successfully']);
    }

    public function toggleActive(PricingPlan $plan): JsonResponse
    {
        return $this->ok(new PricingPlanResource($this->pricing->toggleActive($plan)));
    }
}
