<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Partnership\ConfirmPartnershipRequest;
use App\Http\Requests\Api\V1\Partnership\IndexFarmerPartnershipRequest;
use App\Http\Resources\Api\V1\PartnershipResource;
use App\Models\Partnership;
use App\Models\User;
use App\Services\PartnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FarmerPartnershipController extends Controller
{
    public function __construct(private readonly PartnershipService $partnershipService) {}

    public function index(IndexFarmerPartnershipRequest $request): AnonymousResourceCollection
    {
        $farmer = $request->user();
        abort_unless($farmer instanceof User, 401);
        $partnerships = $this->partnershipService->paginateForFarmer($farmer, $request->validated());

        return PartnershipResource::collection($partnerships)->additional([
            'success' => true,
            'message' => 'Data kemitraan petambak berhasil diambil.',
            'summary' => $this->partnershipService->summaryForFarmer($farmer),
        ]);
    }

    public function show(IndexFarmerPartnershipRequest $request, Partnership $partnership): JsonResponse
    {
        $farmer = $request->user();
        abort_unless($farmer instanceof User, 401);

        return response()->json(['success' => true, 'message' => 'Detail kemitraan berhasil diambil.', 'data' => new PartnershipResource($this->partnershipService->findForFarmerOrFail($farmer, $partnership))]);
    }

    public function confirm(ConfirmPartnershipRequest $request, Partnership $partnership): JsonResponse
    {
        $farmer = $request->user();
        abort_unless($farmer instanceof User, 401);

        return response()->json(['success' => true, 'message' => 'Pengajuan kemitraan berhasil dikonfirmasi.', 'data' => new PartnershipResource($this->partnershipService->confirmForFarmer($farmer, $partnership))]);
    }
}
