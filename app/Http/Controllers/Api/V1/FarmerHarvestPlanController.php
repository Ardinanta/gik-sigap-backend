<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FarmerHarvestPlan\IndexFarmerHarvestPlanRequest;
use App\Http\Requests\Api\V1\FarmerHarvestPlan\StoreFarmerHarvestPlanRequest;
use App\Http\Requests\Api\V1\FarmerHarvestPlan\UpdateFarmerHarvestPlanRequest;
use App\Http\Resources\Api\V1\FarmerHarvestPlanResource;
use App\Models\HarvestPlan;
use App\Models\User;
use App\Services\FarmerHarvestPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FarmerHarvestPlanController extends Controller
{
    public function __construct(private readonly FarmerHarvestPlanService $service) {}

    public function index(IndexFarmerHarvestPlanRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $plans = $user->harvestPlans()
            ->with(['location', 'fishSize'])
            ->when($request->validated('status'), fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('harvest_date')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return FarmerHarvestPlanResource::collection($plans)->additional([
            'success' => true,
            'message' => 'Rencana panen berhasil diambil.',
        ]);
    }

    public function store(StoreFarmerHarvestPlanRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $plan = $this->service->create($user, $request->safe()->except('photo'), $request->file('photo'));

        return response()->json([
            'success' => true,
            'message' => 'Rencana panen berhasil disimpan.',
            'data' => new FarmerHarvestPlanResource($plan),
        ], 201);
    }

    public function show(IndexFarmerHarvestPlanRequest $request, HarvestPlan $harvestPlan): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->service->ensureOwner($user, $harvestPlan);

        return response()->json([
            'success' => true,
            'message' => 'Detail rencana panen berhasil diambil.',
            'data' => new FarmerHarvestPlanResource($harvestPlan->load(['location', 'fishSize'])),
        ]);
    }

    public function update(UpdateFarmerHarvestPlanRequest $request, HarvestPlan $harvestPlan): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $plan = $this->service->update($user, $harvestPlan, $request->safe()->except('photo'), $request->file('photo'));

        return response()->json([
            'success' => true,
            'message' => 'Rencana panen berhasil diperbarui.',
            'data' => new FarmerHarvestPlanResource($plan),
        ]);
    }
}
