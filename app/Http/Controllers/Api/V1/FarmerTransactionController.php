<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transaction\IndexFarmerTransactionRequest;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FarmerTransactionController extends Controller
{
    public function __construct(private readonly TransactionService $transactionService) {}

    public function __invoke(IndexFarmerTransactionRequest $request): AnonymousResourceCollection
    {
        $farmer = $request->user();
        abort_unless($farmer instanceof User, 401);

        return TransactionResource::collection($this->transactionService->paginateForFarmer($farmer, $request->validated()))
            ->additional(['success' => true, 'message' => 'Riwayat transaksi petambak berhasil diambil.']);
    }
}
