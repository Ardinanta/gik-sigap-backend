<?php

namespace Database\Seeders;

use App\Models\BuyerDemand;
use App\Models\Commodity;
use App\Models\FishSize;
use App\Models\Location;
use App\Models\User;
use App\Services\MatchingService;
use Illuminate\Database\Seeder;

class BuyerDemandSeeder extends Seeder
{
    public function run(): void
    {
        $buyer = User::query()->where('email', 'pembeli@sigap.test')->firstOrFail();
        $commodity = Commodity::query()->where('code', 'bandeng')->firstOrFail();
        $fishSize = FishSize::query()->where('commodity_id', $commodity->id)->where('code', 'medium')->firstOrFail();
        $location = Location::query()->where('code', 'MANYAR')->firstOrFail();

        $demand = BuyerDemand::query()->updateOrCreate(
            [
                'buyer_id' => $buyer->id,
                'notes' => 'Kebutuhan demo rekomendasi SIGAP.',
            ],
            [
                'target_location_id' => $location->id,
                'commodity_id' => $commodity->id,
                'fish_size_id' => $fishSize->id,
                'required_volume_kg' => 5000,
                'need_start_date' => today()->addDays(5),
                'need_end_date' => today()->addDays(30),
                'status' => 'active',
            ],
        );

        app(MatchingService::class)->generate($buyer, $demand);
    }
}
