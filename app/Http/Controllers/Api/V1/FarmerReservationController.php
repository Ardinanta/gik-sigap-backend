<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reservation\ConfirmReservationRequest;
use App\Http\Requests\Api\V1\Reservation\IndexFarmerReservationRequest;
use App\Http\Resources\Api\V1\ReservationResource;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FarmerReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService) {}

    public function index(IndexFarmerReservationRequest $request): AnonymousResourceCollection
    {
        $farmer = $request->user();
        abort_unless($farmer instanceof User, 401);

        return ReservationResource::collection($this->reservationService->paginateForFarmer($farmer, $request->validated()))
            ->additional(['success' => true, 'message' => 'Permintaan reservasi berhasil diambil.']);
    }

    public function confirm(ConfirmReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $farmer = $request->user();
        abort_unless($farmer instanceof User, 401);

        return response()->json(['success' => true, 'message' => 'Reservasi pembeli berhasil dikonfirmasi.', 'data' => new ReservationResource($this->reservationService->confirmForFarmer($farmer, $reservation))]);
    }
}
