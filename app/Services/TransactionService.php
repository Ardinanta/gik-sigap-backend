<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransactionService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Transaction>
     */
    public function paginateForBuyer(User $buyer, array $filters): LengthAwarePaginator
    {
        return Transaction::query()
            ->with([
                'seller:id,name',
                'location:id,code,name',
                'commodity:id,code,name',
                'fishSize:id,code,name',
            ])
            ->where('buyer_id', $buyer->id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 12))
            ->withQueryString();
    }

    public function findForBuyerOrFail(User $buyer, Transaction $transaction): Transaction
    {
        return Transaction::query()
            ->with([
                'seller:id,name',
                'location:id,code,name',
                'commodity:id,code,name',
                'fishSize:id,code,name',
            ])
            ->where('buyer_id', $buyer->id)
            ->whereKey($transaction->getKey())
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Transaction>
     */
    public function paginateForFarmer(User $farmer, array $filters): LengthAwarePaginator
    {
        return Transaction::query()
            ->with(['buyer:id,name', 'location:id,code,name', 'commodity:id,code,name', 'fishSize:id,code,name'])
            ->where('seller_id', $farmer->id)
            ->orderByDesc('transaction_date')->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 12))->withQueryString();
    }
}
