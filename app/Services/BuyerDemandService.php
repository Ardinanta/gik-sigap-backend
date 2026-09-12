<?php

namespace App\Services;

use App\Models\BuyerDemand;
use App\Models\Commodity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    public function delete(User $buyer, BuyerDemand $demand): void
    {
        abort_unless((int) $demand->buyer_id === (int) $buyer->id, 404);

        DB::transaction(function () use ($buyer, $demand): void {
            $lockedDemand = BuyerDemand::query()
                ->whereKey($demand->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless((int) $lockedDemand->buyer_id === (int) $buyer->id, 404);

            if ($lockedDemand->status !== 'active') {
                throw ValidationException::withMessages([
                    'demand' => 'Hanya kebutuhan aktif yang dapat dihapus.',
                ]);
            }

            $lockedDemand->matches()
                ->where('status', 'recommended')
                ->update(['status' => 'expired']);

            $lockedDemand->update(['status' => 'cancelled']);
            $lockedDemand->delete();
        });
    }
}
