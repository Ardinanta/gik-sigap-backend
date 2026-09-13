<?php

namespace App\Services;

use App\Models\Partnership;
use App\Models\User;
use App\Notifications\PartnershipActivityNotification;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class HandoverService
{
    public function details(Partnership $partnership): Partnership
    {
        return $partnership->load([
            ...Partnership::reservationRelations(),
            'matchResult.buyerDemand.buyer:id,name,phone',
            'matchResult.harvestPlan.farmer:id,name,phone',
            'matchResult.harvestPlan.location:id,code,name',
            'matchResult.harvestPlan.commodity:id,code,name',
            'matchResult.harvestPlan.fishSize:id,code,name',
            'histories.changedBy:id,name',
            'transaction.seller:id,name',
            'transaction.location:id,code,name',
            'transaction.commodity:id,code,name',
            'transaction.fishSize:id,code,name',
        ]);
    }

    public function confirm(User $actor, Partnership $partnership, string $volume, int $version): Partnership
    {
        return DB::transaction(function () use ($actor, $partnership, $volume, $version): Partnership {
            $locked = Partnership::query()->whereKey($partnership->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('handover', $locked);
            $locked->loadMissing([
                'matchResult.buyerDemand',
                'matchResult.harvestPlan',
                'reservation.buyer',
                'reservation.harvestPlan',
            ]);
            $plan = $locked->supplyPlan();
            $buyer = $locked->purchasingBuyer();
            abort_unless($plan !== null && $buyer !== null, 404);
            abort_unless(
                (int) $actor->id === (int) $buyer->id
                || (int) $actor->id === (int) $plan->farmer_id,
                404,
            );
            $side = (int) $buyer->id === (int) $actor->id ? 'buyer' : 'seller';
            $weightKey = $side.'_weight_kg';
            $timeKey = $side.'_confirmed_at';
            $normalized = (string) BigDecimal::of($volume)->toScale(2);

            if ($locked->status === 'completed') {
                if ($locked->{$weightKey} === $normalized && $locked->{$timeKey} !== null) {
                    return $this->details($locked);
                }
                throw ValidationException::withMessages(['volume_kg' => 'Transaksi selesai tidak dapat diubah.']);
            }

            if ($locked->status !== 'matched' || $locked->agreed_volume_kg === null || $locked->agreed_price_per_kg === null || $locked->agreed_price_per_kg <= 0) {
                throw ValidationException::withMessages(['volume_kg' => 'Penyerahan hanya dapat dikonfirmasi setelah volume dan harga kemitraan disepakati.']);
            }

            if ($plan->harvest_date->isAfter(today())) {
                throw ValidationException::withMessages([
                    'volume_kg' => 'Konfirmasi penyerahan tersedia mulai tanggal panen '.$plan->harvest_date->format('d-m-Y').'.',
                ]);
            }

            abort_if($version !== $locked->handover_version, 409, 'Data penyerahan telah berubah. Muat ulang dan periksa berat terbaru.');
            $locked->{$weightKey} = $normalized;
            $locked->{$timeKey} = now();
            $locked->handover_version++;
            $completed = $locked->seller_confirmed_at !== null
                && $locked->buyer_confirmed_at !== null
                && $locked->seller_weight_kg === $locked->buyer_weight_kg;

            if ($completed) {
                $locked->status = 'completed';
                $locked->completed_at = now();
            }
            $locked->save();

            $locked->histories()->create([
                'changed_by_user_id' => $actor->id,
                'from_status' => 'matched',
                'to_status' => $locked->status,
                'note' => ($side === 'buyer' ? 'Pembeli' : 'Petambak').' mengonfirmasi berat '.$normalized.' kg.'
                    .($completed ? ' Kedua pihak sepakat; serah terima selesai.' : ''),
                'changed_at' => now(),
            ]);

            if ($completed) {
                $locked->transaction()->create([
                    'seller_id' => $plan->farmer_id,
                    'buyer_id' => $buyer->id,
                    'location_id' => $plan->location_id,
                    'commodity_id' => $plan->commodity_id,
                    'fish_size_id' => $plan->fish_size_id,
                    'volume_kg' => $normalized,
                    'price_per_kg' => $locked->agreed_price_per_kg,
                    'transaction_date' => now('Asia/Jakarta')->toDateString(),
                    'recorded_by' => $actor->id,
                    'locked_at' => now(),
                ]);
            }

            $recipient = $side === 'buyer'
                ? $plan->farmer()->first()
                : $buyer;
            $recipientRole = $side === 'buyer' ? 'farmer' : 'buyer';
            $recipient?->notify(new PartnershipActivityNotification(
                $completed ? 'handover_completed' : 'handover_confirmed',
                $completed ? 'Serah terima selesai' : 'Konfirmasi berat diterima',
                $completed
                    ? "Kedua pihak menyepakati berat {$normalized} kg. Transaksi telah selesai."
                    : ($side === 'buyer' ? 'Pembeli' : 'Petambak')." mengonfirmasi berat {$normalized} kg. Silakan periksa dan konfirmasi.",
                "/app/{$recipientRole}/kemitraan/{$locked->id}/serah-terima",
                $locked->id,
            ));

            return $this->details($locked);
        }, attempts: 3);
    }
}
