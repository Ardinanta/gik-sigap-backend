<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Risk\IndexFarmerRiskRequest;
use App\Models\User;
use App\Services\FarmerRiskService;
use Illuminate\Http\JsonResponse;

class FarmerRiskController extends Controller
{
    public function __construct(private readonly FarmerRiskService $service) {}

    public function __invoke(IndexFarmerRiskRequest $request): JsonResponse
    {
        $farmer = $request->user();
        abort_unless($farmer instanceof User, 401);

        return response()->json([
            'success' => true,
            'message' => 'Risiko panen berhasil dihitung.',
            'data' => $this->service->dashboard($farmer, $request->validated('week')),
        ]);
    }
}
