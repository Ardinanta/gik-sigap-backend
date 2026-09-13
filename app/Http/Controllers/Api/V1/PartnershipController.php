<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Partnership\IndexPartnershipRequest;
use App\Http\Requests\Api\V1\Partnership\StorePartnershipRequest;
use App\Http\Resources\Api\V1\PartnershipHistoryResource;
use App\Http\Resources\Api\V1\PartnershipResource;
use App\Models\MatchResult;
use App\Models\Partnership;
use App\Models\User;
use App\Services\PartnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartnershipController extends Controller
{
    public function __construct(private readonly PartnershipService $partnershipService) {}

    public function index(IndexPartnershipRequest $request): AnonymousResourceCollection
    {
        $buyer = $request->user();
        abort_unless($buyer instanceof User, 401);

        $partnerships = $this->partnershipService->paginateForBuyer($buyer, $request->validated());

        return PartnershipResource::collection($partnerships)->additional([
            'success' => true,
            'message' => 'Data kemitraan berhasil diambil.',
            'summary' => $this->partnershipService->summaryForBuyer($buyer),
        ]);
    }

    public function store(StorePartnershipRequest $request, MatchResult $matchResult): JsonResponse
    {
        $buyer = $request->user();
        abort_unless($buyer instanceof User, 401);

        $partnership = $this->partnershipService->create($buyer, $matchResult);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan kemitraan berhasil dikirim.',
            'data' => new PartnershipResource($partnership),
        ], 201);
    }

    public function show(Request $request, Partnership $partnership): JsonResponse
    {
        $buyer = $this->buyerFrom($request);
        $partnership = $this->partnershipService->findForBuyerOrFail($buyer, $partnership);

        return response()->json([
            'success' => true,
            'message' => 'Detail kemitraan berhasil diambil.',
            'data' => new PartnershipResource($partnership),
        ]);
    }

    public function history(Request $request, Partnership $partnership): JsonResponse
    {
        $buyer = $this->buyerFrom($request);
        $partnership = $this->partnershipService->findForBuyerOrFail($buyer, $partnership);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat status kemitraan berhasil diambil.',
            'data' => PartnershipHistoryResource::collection($partnership->histories),
        ]);
    }

    private function buyerFrom(Request $request): User
    {
        $buyer = $request->user();
        abort_unless($buyer instanceof User, 401);
        abort_unless($buyer->hasRole('buyer'), 403);

        return $buyer;
    }
}
