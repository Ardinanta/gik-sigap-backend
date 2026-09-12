<?php

namespace Database\Seeders;

use App\Models\Commodity;
use App\Models\FishSize;
use App\Models\HarvestPlan;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;

class HarvestPlanSeeder extends Seeder
{
    public function run(): void
    {
        $farmer = User::query()->where('email', 'petambak@sigap.test')->firstOrFail();
        $commodity = Commodity::query()->where('code', 'bandeng')->firstOrFail();

        $plans = [
            ['pond_name' => 'Tambak Sumber Rejeki', 'location' => 'MANYAR', 'size' => 'medium', 'days' => 7, 'volume' => 3200, 'price' => 31500, 'status' => 'planned'],
            ['pond_name' => 'Tambak Mina Bahari', 'location' => 'BUNGAH', 'size' => 'large', 'days' => 12, 'volume' => 1850, 'price' => 34000, 'status' => 'planned'],
            ['pond_name' => 'Tambak Pesisir Makmur', 'location' => 'SIDAYU', 'size' => 'small', 'days' => 18, 'volume' => 4500, 'price' => null, 'status' => 'planned'],
            ['pond_name' => 'Tambak Ujung Jaya', 'location' => 'UJUNGPANGKAH', 'size' => 'medium', 'days' => 25, 'volume' => 2700, 'price' => 32500, 'status' => 'planned'],
            ['pond_name' => 'Tambak Panen Selesai', 'location' => 'MANYAR', 'size' => 'large', 'days' => 3, 'volume' => 1000, 'price' => 33000, 'status' => 'completed'],
        ];

        foreach ($plans as $plan) {
            $location = Location::query()->where('code', $plan['location'])->firstOrFail();
            $fishSize = FishSize::query()
                ->where('commodity_id', $commodity->id)
                ->where('code', $plan['size'])
                ->firstOrFail();

            HarvestPlan::query()->updateOrCreate(
                ['farmer_id' => $farmer->id, 'pond_name' => $plan['pond_name']],
                [
                    'location_id' => $location->id,
                    'commodity_id' => $commodity->id,
                    'fish_size_id' => $fishSize->id,
                    'harvest_date' => today()->addDays($plan['days']),
                    'estimated_volume_kg' => $plan['volume'],
                    'asking_price_per_kg' => $plan['price'],
                    'pond_address' => 'Kabupaten Gresik, Jawa Timur',
                    'status' => $plan['status'],
                    'notes' => 'Pasokan bandeng air payau. Jadwal dapat didiskusikan dengan petambak.',
                ],
            );
        }
    }
}
