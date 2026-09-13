<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reservation\DestroyReservationRequest;
use App\Http\Requests\Api\V1\Reservation\IndexReservationRequest;
use App\Http\Requests\Api\V1\Reservation\StoreReservationRequest;
use App\Http\Resources\Api\V1\ReservationResource;
use App\Models\HarvestPlan;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService) {}

    public function index(IndexReservationRequest $request): AnonymousResourceCollection
    {
        $buyer = $request->user();
        abort_unless($buyer instanceof User, 401);

        $reservations = $this->reservationService->paginateForBuyer($buyer, $request->validated());

        return ReservationResource::collection($reservations)->additional([
            'success' => true,
            'message' => 'Data reservasi berhasil diambil.',
        ]);
    }

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

    public function destroy(DestroyReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $buyer = $request->user();
        abort_unless($buyer instanceof User, 401);

        $reservation = $this->reservationService->cancel($buyer, $reservation);

        return response()->json([
            'success' => true,
            'message' => 'Reservasi berhasil dibatalkan.',
            'data' => new ReservationResource($reservation),
        ]);
    }
}
