<?php

namespace App\Services;

use App\Models\BuyerDemand;
use App\Models\Commodity;
use App\Models\User;
use RuntimeException;

class BuyerDemandService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $buyer, array $data): BuyerDemand
    {
        $commodityId = Commodity::query()
            ->where('code', 'bandeng')
            ->where('is_active', true)
            ->value('id');

        if (! $commodityId) {
            throw new RuntimeException('Komoditas Bandeng belum dikonfigurasi.');
        }

        $demand = $buyer->buyerDemands()->create([
            ...$data,
            'commodity_id' => $commodityId,
            'status' => 'active',
        ]);

        return $demand->load(['fishSize', 'targetLocation']);
    }
}
