<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminRiskRequest;
use App\Models\RiskAssessment;
use App\Models\User;
use App\Services\AdminRiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRiskController extends Controller
{
    public function __construct(private readonly AdminRiskService $service) {}

    public function index(AdminRiskRequest $request): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Risiko panen serentak berhasil dimuat.', 'data' => $this->service->index($request->validated('week'))]);
    }

    public function show(RiskAssessment $riskAssessment): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Detail risiko berhasil dimuat.', 'data' => $this->service->show($riskAssessment)]);
    }

    public function coordinate(Request $request, RiskAssessment $riskAssessment): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof User, 401);
        $assessment = $this->service->coordinate($admin, $riskAssessment);

        return response()->json(['success' => true, 'message' => 'Wilayah telah ditandai dikoordinasikan.', 'data' => $this->service->show($assessment)]);
    }
}
