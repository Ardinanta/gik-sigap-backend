<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Partnership\ConfirmHandoverRequest;
use App\Http\Resources\Api\V1\PartnershipResource;
use App\Models\Partnership;
use App\Services\HandoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class HandoverController extends Controller
{
    public function __construct(private readonly HandoverService $service) {}

    public function show(Partnership $partnership): JsonResponse
    {
        Gate::authorize('handover', $partnership);

        return response()->json([
            'success' => true,
            'message' => 'Data penyerahan berhasil diambil.',
            'data' => new PartnershipResource($this->service->details($partnership)),
        ]);
    }

    public function store(ConfirmHandoverRequest $request, Partnership $partnership): JsonResponse
    {
        $data = $request->validated();
        $result = $this->service->confirm($request->user(), $partnership, (string) $data['volume_kg'], (int) $data['version']);

        return response()->json([
            'success' => true,
            'message' => $result->status === 'completed'
                ? 'Penyerahan selesai. Transaksi berhasil dicatat.'
                : 'Berat tersimpan. Penyerahan selesai setelah kedua pihak menyetujui berat yang sama.',
            'data' => new PartnershipResource($result),
        ]);
    }
}
