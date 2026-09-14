<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminReportRequest;
use App\Models\Transaction;
use App\Services\AdminReportService;
use Illuminate\Http\JsonResponse;

class AdminTransactionController extends Controller
{
    public function __construct(private readonly AdminReportService $service) {}

    public function __invoke(AdminReportRequest $request): JsonResponse
    {
        $filters = $request->safe()->except('status');
        $transactions = $this->service->paginateTransactions($filters);

        return response()->json([
            'success' => true, 'message' => 'Data harga dan transaksi berhasil dimuat.',
            'data' => $transactions->getCollection()->map(fn (Transaction $transaction) => $this->service->transactionData($transaction))->values(),
            'summary' => $this->service->transactionSummary($filters), 'price_trend' => $this->service->priceTrend($filters),
            'meta' => ['current_page' => $transactions->currentPage(), 'last_page' => $transactions->lastPage(), 'per_page' => $transactions->perPage(), 'total' => $transactions->total()],
        ]);
    }
}
