<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reservation\StoreReservationRequest;
use App\Http\Resources\Api\V1\ReservationResource;
use App\Models\HarvestPlan;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService) {}

    public function store(StoreReservationRequest $request, HarvestPlan $harvestPlan): JsonResponse
    {
        $buyer = $request->user();
        abort_unless($buyer instanceof User, 401);

        $reservation = $this->reservationService->create($buyer, $harvestPlan, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan reservasi berhasil dikirim.',
            'data' => new ReservationResource($reservation),
        ], 201);
    }
}
