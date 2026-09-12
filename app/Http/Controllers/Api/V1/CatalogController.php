<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\IndexCatalogRequest;
use App\Http\Resources\Api\V1\CatalogResource;
use App\Models\HarvestPlan;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function __construct(private readonly CatalogService $catalogService) {}

    public function index(IndexCatalogRequest $request): AnonymousResourceCollection
    {
        $plans = $this->catalogService->paginate($request->validated());

        return CatalogResource::collection($plans)->additional([
            'success' => true,
            'message' => 'Data pasokan berhasil diambil.',
        ]);
    }

    public function show(IndexCatalogRequest $request, HarvestPlan $harvestPlan): JsonResponse
    {
        $plan = $this->catalogService->findAvailableOrFail($harvestPlan);

        return response()->json([
            'success' => true,
            'message' => 'Detail pasokan berhasil diambil.',
            'data' => new CatalogResource($plan),
        ]);
    }
}
