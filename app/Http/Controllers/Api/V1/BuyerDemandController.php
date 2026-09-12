<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BuyerDemand\IndexBuyerDemandRequest;
use App\Http\Requests\Api\V1\BuyerDemand\StoreBuyerDemandRequest;
use App\Http\Resources\Api\V1\BuyerDemandResource;
use App\Models\User;
use App\Services\BuyerDemandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuyerDemandController extends Controller
{
    public function __construct(private readonly BuyerDemandService $buyerDemandService) {}

    public function index(IndexBuyerDemandRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $demands = $user->buyerDemands()
            ->with(['fishSize', 'targetLocation'])
            ->when($request->validated('status'), fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return BuyerDemandResource::collection($demands)->additional([
            'success' => true,
            'message' => 'Data kebutuhan berhasil diambil.',
        ]);
    }

    public function store(StoreBuyerDemandRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $demand = $this->buyerDemandService->create($user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Kebutuhan bandeng berhasil disimpan.',
            'data' => new BuyerDemandResource($demand),
        ], 201);
    }
}
