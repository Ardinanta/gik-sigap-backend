<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminReportRequest;
use App\Services\AdminReportService;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __construct(private readonly AdminReportService $service) {}

    public function __invoke(AdminReportRequest $request): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Dashboard Admin berhasil dimuat.', 'data' => $this->service->dashboard($request->validated())]);
    }
}
