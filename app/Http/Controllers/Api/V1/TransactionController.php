<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transaction\IndexTransactionRequest;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $transactionService) {}

    public function index(IndexTransactionRequest $request): AnonymousResourceCollection
    {
        $buyer = $request->user();
        abort_unless($buyer instanceof User, 401);

        $transactions = $this->transactionService->paginateForBuyer($buyer, $request->validated());

        return TransactionResource::collection($transactions)->additional([
            'success' => true,
            'message' => 'Riwayat transaksi berhasil diambil.',
        ]);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $buyer = $request->user();
        abort_unless($buyer instanceof User, 401);
        abort_unless($buyer->hasRole('buyer'), 403);

        $transaction = $this->transactionService->findForBuyerOrFail($buyer, $transaction);

        return response()->json([
            'success' => true,
            'message' => 'Detail transaksi berhasil diambil.',
            'data' => new TransactionResource($transaction),
        ]);
    }
}
