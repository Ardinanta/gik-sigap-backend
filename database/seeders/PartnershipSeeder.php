<?php

namespace Database\Seeders;

use App\Models\BuyerDemand;
use App\Models\HarvestPlan;
use App\Models\MatchResult;
use App\Models\Partnership;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PartnershipSeeder extends Seeder
{
    public function run(): void
    {
        $primaryBuyer = User::query()->where('email', 'pembeli@sigap.test')->firstOrFail();
        $primaryDemand = $this->demand('[DEMO-KEMITRAAN-UTAMA]');
        $primaryMatches = $this->matchesFor($primaryDemand, 3);

        DB::transaction(function () use ($primaryBuyer, $primaryDemand, $primaryMatches): void {
            $this->seedPartnership($primaryMatches->get(0), $primaryBuyer, 'matched', 'Kemitraan aktif menunggu konfirmasi pembeli.', 900, 0);
            $this->seedPartnership($primaryMatches->get(1), $primaryBuyer, 'completed', 'Transaksi demo utama telah selesai.', 750, 3);
            $this->seedPartnership($primaryMatches->get(2), $primaryBuyer, 'interested', 'Pengajuan kemitraan baru menunggu tinjauan petambak.', null, 0);

            $reservationPlan = HarvestPlan::query()
                ->where('pond_name', 'Tambak Ujung Jaya')
                ->firstOrFail();

            Reservation::query()->updateOrCreate(
                [
                    'harvest_plan_id' => $reservationPlan->id,
                    'buyer_id' => $primaryBuyer->id,
                    'notes' => '[DEMO-RESERVASI-PENDING] Pengajuan untuk kebutuhan restoran mitra.',
                ],
                [
                    'buyer_demand_id' => $primaryDemand->id,
                    'reserved_volume_kg' => 450,
                    'status' => 'pending',
                    'expires_at' => now()->addDays(7),
                    'confirmed_at' => null,
                    'cancelled_at' => null,
                ],
            );
        });

        $koperasiBuyer = User::query()->where('email', 'koperasi@sigap.test')->firstOrFail();
        $koperasiMediumMatches = $this->matchesFor($this->demand('[DEMO-KOPERASI-MEDIUM]'), 2);
        $koperasiLargeMatch = $this->matchesFor($this->demand('[DEMO-KOPERASI-LARGE]'), 1)->first();

        DB::transaction(function () use ($koperasiBuyer, $koperasiLargeMatch, $koperasiMediumMatches): void {
            $this->seedPartnership($koperasiMediumMatches->get(0), $koperasiBuyer, 'completed', 'Distribusi koperasi minggu lalu telah selesai.', 1100, 9);
            $this->seedPartnership($koperasiMediumMatches->get(1), $koperasiBuyer, 'discussing', 'Harga dan jadwal pengiriman sedang dibahas.', null, 0);
            $this->seedPartnership($koperasiLargeMatch, $koperasiBuyer, 'completed', 'Pesanan bandeng besar koperasi telah diterima.', 900, 16);
        });

        $rinaBuyer = User::query()->where('email', 'rina@sigap.test')->firstOrFail();
        $rinaMatch = $this->matchesFor($this->demand('[DEMO-DISTRIBUTOR-MEDIUM]'), 1)->first();

        DB::transaction(function () use ($rinaBuyer, $rinaMatch): void {
            $this->seedPartnership($rinaMatch, $rinaBuyer, 'cancelled', 'Pengajuan dibatalkan karena jadwal distribusi berubah.', null, 0);
        });
    }

    private function demand(string $marker): BuyerDemand
    {
        return BuyerDemand::query()->where('notes', 'like', "%{$marker}%")->firstOrFail();
    }

    /** @return Collection<int, MatchResult> */
    private function matchesFor(BuyerDemand $demand, int $minimum): Collection
    {
        $matches = MatchResult::query()
            ->with(['harvestPlan', 'buyerDemand'])
            ->where('buyer_demand_id', $demand->id)
            ->orderByDesc('match_score')
            ->orderBy('id')
            ->get();

        if ($matches->count() < $minimum) {
            throw new RuntimeException("PartnershipSeeder membutuhkan {$minimum} rekomendasi untuk kebutuhan #{$demand->id}.");
        }

        return $matches;
    }

    private function seedPartnership(
        MatchResult $match,
        User $buyer,
        string $status,
        string $notes,
        ?float $agreedVolume,
        int $completedDaysAgo,
    ): Partnership {
        $plan = $match->harvestPlan;
        $startedAt = $completedDaysAgo > 0 ? now()->subDays($completedDaysAgo + 7) : now()->subDays(2);
        $completedAt = $status === 'completed' ? now()->subDays($completedDaysAgo) : null;
        $cancelledAt = $status === 'cancelled' ? now()->subDay() : null;
        $finalVolume = $agreedVolume ?? min((float) $match->matched_volume_kg, 600);
        $hasAgreement = in_array($status, ['matched', 'completed'], true);

        $partnership = Partnership::query()->updateOrCreate(
            ['match_id' => $match->id],
            [
                'reservation_id' => null,
                'initiated_by_user_id' => $buyer->id,
                'status' => $status,
                'agreed_volume_kg' => $hasAgreement ? $finalVolume : null,
                'agreed_price_per_kg' => $hasAgreement ? $plan->asking_price_per_kg : null,
                'notes' => "[DEMO-{$status}] {$notes}",
                'seller_weight_kg' => $status === 'matched' || $status === 'completed' ? $finalVolume : null,
                'buyer_weight_kg' => $status === 'completed' ? $finalVolume : null,
                'seller_confirmed_at' => $status === 'matched' || $status === 'completed' ? ($completedAt ?? now()->subHours(3)) : null,
                'buyer_confirmed_at' => $status === 'completed' ? $completedAt : null,
                'handover_version' => $status === 'completed' ? 2 : ($status === 'matched' ? 1 : 0),
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'cancelled_at' => $cancelledAt,
            ],
        );

        $this->seedHistory($partnership, $buyer, null, 'interested', 'Kemitraan diajukan oleh pembeli.', $startedAt);

        if ($status === 'discussing') {
            $this->seedHistory($partnership, $buyer, 'interested', 'discussing', 'Pembeli dan petambak mulai mendiskusikan harga serta jadwal.', now()->subDay());
        }

        if (in_array($status, ['matched', 'completed'], true)) {
            $this->seedHistory($partnership, $plan->farmer, 'interested', 'matched', 'Volume dan harga disepakati oleh kedua pihak.', $completedAt?->copy()->subDays(2) ?? now()->subDay());
        }

        if ($status === 'completed') {
            $this->seedHistory($partnership, $buyer, 'matched', 'completed', 'Serah terima dikonfirmasi dan transaksi diselesaikan.', $completedAt);
            Transaction::query()->updateOrCreate(
                ['partnership_id' => $partnership->id],
                [
                    'seller_id' => $plan->farmer_id,
                    'buyer_id' => $buyer->id,
                    'location_id' => $plan->location_id,
                    'commodity_id' => $plan->commodity_id,
                    'fish_size_id' => $plan->fish_size_id,
                    'volume_kg' => $finalVolume,
                    'price_per_kg' => $plan->asking_price_per_kg,
                    'transaction_date' => today()->subDays($completedDaysAgo),
                    'recorded_by' => $buyer->id,
                    'locked_at' => $completedAt,
                ],
            );
        } else {
            Transaction::query()->where('partnership_id', $partnership->id)->delete();
        }

        if ($status === 'cancelled') {
            $this->seedHistory($partnership, $buyer, 'interested', 'cancelled', 'Pengajuan dibatalkan karena jadwal distribusi berubah.', $cancelledAt);
        }

        $match->update(['status' => 'accepted']);

        return $partnership;
    }

    private function seedHistory(
        Partnership $partnership,
        User $actor,
        ?string $fromStatus,
        string $toStatus,
        string $note,
        $changedAt,
    ): void {
        $partnership->histories()->updateOrCreate(
            ['to_status' => $toStatus, 'note' => $note],
            [
                'changed_by_user_id' => $actor->id,
                'from_status' => $fromStatus,
                'changed_at' => $changedAt,
            ],
        );
    }
}
