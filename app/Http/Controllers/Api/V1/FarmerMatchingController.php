<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Matching\IndexFarmerMatchingRequest;
use App\Http\Resources\Api\V1\FarmerMatchingResource;
use App\Models\User;
use App\Services\MatchingService;
use Illuminate\Http\JsonResponse;

class FarmerMatchingController extends Controller
{
    public function __construct(private readonly MatchingService $matchingService) {}

    public function __invoke(IndexFarmerMatchingRequest $request): JsonResponse
    {
        $farmer = $request->user();
        abort_unless($farmer instanceof User, 401);

        $matches = $this->matchingService->paginateForFarmer(
            $farmer,
            $request->integer('harvest_plan_id') ?: null,
            $request->integer('per_page', 12),
        );

        return response()->json([
            'success' => true,
            'message' => 'Rekomendasi pembeli berhasil diambil.',
            'data' => FarmerMatchingResource::collection($matches->items())->resolve($request),
            'meta' => [
                'current_page' => $matches->currentPage(),
                'last_page' => $matches->lastPage(),
                'per_page' => $matches->perPage(),
                'total' => $matches->total(),
            ],
        ]);
    }
}
