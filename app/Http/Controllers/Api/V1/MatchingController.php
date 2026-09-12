<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Matching\GenerateMatchingRequest;
use App\Http\Requests\Api\V1\Matching\IndexMatchingRequest;
use App\Http\Resources\Api\V1\BuyerDemandResource;
use App\Http\Resources\Api\V1\MatchingResource;
use App\Models\BuyerDemand;
use App\Models\MatchResult;
use App\Models\User;
use App\Services\MatchingService;
use Illuminate\Http\JsonResponse;

class MatchingController extends Controller
{
    public function __construct(private readonly MatchingService $matchingService) {}

    public function index(IndexMatchingRequest $request, BuyerDemand $buyerDemand): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $demand = $this->matchingService->demandForBuyer($user, $buyerDemand);
        $matches = $this->matchingService->paginate($user, $demand, $request->integer('per_page', 12));

        return response()->json([
            'success' => true,
            'message' => 'Rekomendasi berhasil diambil.',
            'demand' => new BuyerDemandResource($demand),
            'data' => MatchingResource::collection($matches->items())->resolve($request),
            'meta' => [
                'current_page' => $matches->currentPage(),
                'last_page' => $matches->lastPage(),
                'per_page' => $matches->perPage(),
                'total' => $matches->total(),
            ],
        ]);
    }

    public function generate(GenerateMatchingRequest $request, BuyerDemand $buyerDemand): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $matches = $this->matchingService->generate($user, $buyerDemand);
        $demand = $this->matchingService->demandForBuyer($user, $buyerDemand);

        return response()->json([
            'success' => true,
            'message' => 'Rekomendasi berhasil disinkronkan.',
            'demand' => new BuyerDemandResource($demand),
            'data' => MatchingResource::collection($matches)->resolve($request),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $matches->count(),
                'total' => $matches->count(),
            ],
        ]);
    }

    public function show(IndexMatchingRequest $request, MatchResult $matchResult): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json([
            'success' => true,
            'message' => 'Detail rekomendasi berhasil diambil.',
            'data' => new MatchingResource($this->matchingService->findForBuyer($user, $matchResult)),
        ]);
    }
}
