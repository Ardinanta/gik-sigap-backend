<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminReportRequest;
use App\Models\HarvestPlan;
use App\Services\AdminReportService;
use Illuminate\Http\JsonResponse;

class AdminHarvestPlanController extends Controller
{
    public function __construct(private readonly AdminReportService $service) {}

    public function __invoke(AdminReportRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $plans = $this->service->paginateHarvestPlans($filters);

        return response()->json([
            'success' => true, 'message' => 'Rencana panen global berhasil dimuat.',
            'data' => $plans->getCollection()->map(fn (HarvestPlan $plan) => [
                'id' => $plan->id, 'farmer_name' => $plan->farmer?->name, 'pond_name' => $plan->pond_name,
                'location' => ['id' => $plan->location->id, 'code' => $plan->location->code, 'name' => $plan->location->name],
                'fish_size' => ['id' => $plan->fishSize->id, 'code' => $plan->fishSize->code, 'name' => $plan->fishSize->name],
                'harvest_date' => $plan->harvest_date?->format('Y-m-d'), 'estimated_volume_kg' => $plan->estimated_volume_kg,
                'asking_price_per_kg' => $plan->asking_price_per_kg, 'status' => $plan->status,
            ])->values(),
            'summary' => $this->service->harvestSummary($filters),
            'meta' => ['current_page' => $plans->currentPage(), 'last_page' => $plans->lastPage(), 'per_page' => $plans->perPage(), 'total' => $plans->total()],
        ]);
    }
}
