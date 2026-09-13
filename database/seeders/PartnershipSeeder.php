<?php

namespace Database\Seeders;

use App\Models\BuyerDemand;
use App\Models\Commodity;
use App\Models\FishSize;
use App\Models\HarvestPlan;
use App\Models\Location;
use App\Models\MatchResult;
use App\Models\Partnership;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MatchingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PartnershipSeeder extends Seeder
{
    public function run(): void
    {
        $commodity = Commodity::query()->where('code', 'bandeng')->where('is_active', true)->firstOrFail();
        $fishSize = FishSize::query()->where('commodity_id', $commodity->id)->where('code', 'medium')->firstOrFail();
        $location = Location::query()->where('code', 'MANYAR')->where('is_active', true)->firstOrFail();
        $buyers = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('code', 'buyer'))
            ->orderBy('id')
            ->get();

        if ($buyers->isEmpty()) {
            throw new RuntimeException('PartnershipSeeder membutuhkan minimal satu akun Buyer aktif.');
        }

        $readyHarvestPlanIds = [];

        foreach ($buyers as $buyer) {
            $demand = BuyerDemand::query()->updateOrCreate(
                [
                    'buyer_id' => $buyer->id,
                    'notes' => 'Kebutuhan demo halaman Kemitraan.',
                ],
                [
                    'target_location_id' => $location->id,
                    'commodity_id' => $commodity->id,
                    'fish_size_id' => $fishSize->id,
                    'required_volume_kg' => 100,
                    'need_start_date' => today()->addDays(5),
                    'need_end_date' => today()->addDays(30),
                    'status' => 'active',
                    'deleted_at' => null,
                ],
            );

            app(MatchingService::class)->generate($buyer, $demand);
            $matches = $this->matchesFor($demand);

            if ($matches->count() < 3) {
                throw new RuntimeException("PartnershipSeeder membutuhkan tiga rekomendasi aktif untuk Buyer #{$buyer->id}.");
            }

            $readyHarvestPlanIds[] = $this->seedBuyerScenarios($buyer, $matches);
        }

        HarvestPlan::query()
            ->whereIn('id', array_unique($readyHarvestPlanIds))
            ->update(['harvest_date' => today()]);
    }

    private function matchesFor(BuyerDemand $demand)
    {
        return MatchResult::query()
            ->with('harvestPlan')
            ->where('buyer_demand_id', $demand->id)
            ->where('status', 'recommended')
            ->orderByDesc('match_score')
            ->orderBy(
                HarvestPlan::query()->select('harvest_date')->whereColumn('harvest_plans.id', 'matches.harvest_plan_id'),
            )
            ->orderBy('id')
            ->limit(3)
            ->get();
    }

    private function seedBuyerScenarios(User $buyer, $matches): int
    {
        return DB::transaction(function () use ($buyer, $matches): int {
            $activeMatch = $matches->first();
            $active = Partnership::query()->updateOrCreate(
                ['match_id' => $activeMatch->id],
                [
                    'initiated_by_user_id' => $buyer->id,
                    'status' => 'matched',
                    'agreed_volume_kg' => 100,
                    'agreed_price_per_kg' => $activeMatch->harvestPlan->asking_price_per_kg ?? 32000,
                    'notes' => 'Kesepakatan demo kemitraan SIGAP.',
                    'seller_weight_kg' => 100,
                    'buyer_weight_kg' => null,
                    'seller_confirmed_at' => now()->subHours(2),
                    'buyer_confirmed_at' => null,
                    'handover_version' => 1,
                    'started_at' => now()->subDays(2),
                    'completed_at' => null,
                    'cancelled_at' => null,
                ],
            );
            $active->histories()->updateOrCreate(
                ['to_status' => 'matched', 'note' => 'Volume dan harga telah disepakati.'],
                [
                    'changed_by_user_id' => $buyer->id,
                    'from_status' => 'discussing',
                    'changed_at' => now()->subDay(),
                ],
            );
            $active->histories()->updateOrCreate(
                ['to_status' => 'matched', 'note' => 'Petambak mengonfirmasi berat 100.00 kg.'],
                [
                    'changed_by_user_id' => $activeMatch->harvestPlan->farmer_id,
                    'from_status' => 'matched',
                    'changed_at' => now()->subHours(2),
                ],
            );
            $activeMatch->update(['status' => 'accepted']);

            $reservationMatch = $matches->get(1);
            Reservation::query()->updateOrCreate(
                [
                    'harvest_plan_id' => $reservationMatch->harvest_plan_id,
                    'buyer_id' => $buyer->id,
                    'notes' => 'Reservasi demo halaman Kemitraan.',
                ],
                [
                    'buyer_demand_id' => $reservationMatch->buyer_demand_id,
                    'reserved_volume_kg' => 50,
                    'status' => 'pending',
                    'expires_at' => now()->addDays(7),
                    'confirmed_at' => null,
                    'cancelled_at' => null,
                ],
            );

            $completedMatch = $matches->get(2);
            $completed = Partnership::query()->updateOrCreate(
                ['match_id' => $completedMatch->id],
                [
                    'initiated_by_user_id' => $buyer->id,
                    'status' => 'completed',
                    'agreed_volume_kg' => 100,
                    'agreed_price_per_kg' => $completedMatch->harvestPlan->asking_price_per_kg ?? 32000,
                    'notes' => 'Transaksi demo telah selesai.',
                    'seller_weight_kg' => 100,
                    'buyer_weight_kg' => 100,
                    'seller_confirmed_at' => now()->subDays(3)->subHour(),
                    'buyer_confirmed_at' => now()->subDays(3),
                    'handover_version' => 2,
                    'started_at' => now()->subDays(20),
                    'completed_at' => now()->subDays(3),
                    'cancelled_at' => null,
                ],
            );
            $completed->histories()->updateOrCreate(
                ['to_status' => 'completed'],
                [
                    'changed_by_user_id' => $buyer->id,
                    'from_status' => 'matched',
                    'note' => 'Transaksi telah diselesaikan.',
                    'changed_at' => now()->subDays(3),
                ],
            );
            $completedMatch->update(['status' => 'accepted']);

            $plan = $completedMatch->harvestPlan;
            Transaction::query()->updateOrCreate(
                ['partnership_id' => $completed->id],
                [
                    'seller_id' => $plan->farmer_id,
                    'buyer_id' => $buyer->id,
                    'location_id' => $plan->location_id,
                    'commodity_id' => $plan->commodity_id,
                    'fish_size_id' => $plan->fish_size_id,
                    'volume_kg' => $completed->agreed_volume_kg,
                    'price_per_kg' => $completed->agreed_price_per_kg,
                    'transaction_date' => today()->subDays(3),
                    'recorded_by' => $buyer->id,
                    'locked_at' => now()->subDays(3),
                ],
            );

            return $activeMatch->harvest_plan_id;
        });
    }
}
