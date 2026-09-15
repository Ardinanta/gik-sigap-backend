<?php

namespace Database\Seeders;

use App\Models\Commodity;
use App\Models\FishSize;
use App\Models\HarvestPlan;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class HarvestPlanSeeder extends Seeder
{
    public function run(): void
    {
        $commodity = Commodity::query()->where('code', 'bandeng')->firstOrFail();
        $weekStart = CarbonImmutable::today()->startOfWeek(CarbonImmutable::MONDAY);
        $today = CarbonImmutable::today();

        $plans = [
            ['farmer' => 'petambak@sigap.test', 'pond' => 'Tambak Sumber Rejeki', 'location' => 'MANYAR', 'size' => 'medium', 'date' => $weekStart->addDays(1), 'volume' => 3600, 'price' => 31500, 'latitude' => -7.1034100, 'longitude' => 112.6012300, 'status' => 'planned'],
            ['farmer' => 'petambak@sigap.test', 'pond' => 'Tambak Harapan Jaya', 'location' => 'MANYAR', 'size' => 'medium', 'date' => $weekStart->addDays(3), 'volume' => 3000, 'price' => 32000, 'latitude' => -7.0981200, 'longitude' => 112.6078500, 'status' => 'planned'],
            ['farmer' => 'petambak@sigap.test', 'pond' => 'Tambak Segara Wangi', 'location' => 'MANYAR', 'size' => 'medium', 'date' => $today->addDays(12), 'volume' => 2200, 'price' => 32500, 'latitude' => -7.1126400, 'longitude' => 112.5947200, 'status' => 'planned'],
            ['farmer' => 'agus@sigap.test', 'pond' => 'Tambak Mina Bahari', 'location' => 'BUNGAH', 'size' => 'medium', 'date' => $weekStart->addDays(2), 'volume' => 5000, 'price' => 31800, 'latitude' => -6.9821400, 'longitude' => 112.5703100, 'status' => 'planned'],
            ['farmer' => 'agus@sigap.test', 'pond' => 'Tambak Kali Mireng', 'location' => 'BUNGAH', 'size' => 'large', 'date' => $weekStart->addDays(4), 'volume' => 4100, 'price' => 34500, 'latitude' => -6.9905800, 'longitude' => 112.5769400, 'status' => 'planned'],
            ['farmer' => 'agus@sigap.test', 'pond' => 'Tambak Banyu Urip', 'location' => 'BUNGAH', 'size' => 'medium', 'date' => $today->addDays(18), 'volume' => 2800, 'price' => 32200, 'latitude' => -6.9754200, 'longitude' => 112.5631800, 'status' => 'planned'],
            ['farmer' => 'aini@sigap.test', 'pond' => 'Tambak Pesisir Makmur', 'location' => 'SIDAYU', 'size' => 'small', 'date' => $weekStart->addDays(5), 'volume' => 3200, 'price' => 29500, 'latitude' => -6.9987100, 'longitude' => 112.5332800, 'status' => 'planned'],
            ['farmer' => 'aini@sigap.test', 'pond' => 'Tambak Sidayu Sejahtera', 'location' => 'SIDAYU', 'size' => 'medium', 'date' => $today->addDays(8), 'volume' => 2400, 'price' => 31900, 'latitude' => -7.0064200, 'longitude' => 112.5268300, 'status' => 'planned'],
            ['farmer' => 'hasan@sigap.test', 'pond' => 'Tambak Ujung Jaya', 'location' => 'UJUNGPANGKAH', 'size' => 'large', 'date' => $today->addDays(15), 'volume' => 2700, 'price' => 35000, 'latitude' => -6.9285400, 'longitude' => 112.5487600, 'status' => 'planned'],
            ['farmer' => 'hasan@sigap.test', 'pond' => 'Tambak Nelayan Makmur', 'location' => 'UJUNGPANGKAH', 'size' => 'small', 'date' => $today->addDays(24), 'volume' => 1900, 'price' => null, 'latitude' => -6.9362100, 'longitude' => 112.5564100, 'status' => 'planned'],
            ['farmer' => 'petambak@sigap.test', 'pond' => 'Tambak Panen Selesai', 'location' => 'MANYAR', 'size' => 'large', 'date' => $today->subDays(8), 'volume' => 1000, 'price' => 33000, 'latitude' => -7.1056600, 'longitude' => 112.6104200, 'status' => 'completed'],
            ['farmer' => 'agus@sigap.test', 'pond' => 'Tambak Panen Agustus', 'location' => 'BUNGAH', 'size' => 'medium', 'date' => $today->subDays(18), 'volume' => 1500, 'price' => 31000, 'latitude' => -6.9872100, 'longitude' => 112.5681100, 'status' => 'completed'],
        ];

        $regionalFarmers = [
            'petambak@sigap.test',
            'agus@sigap.test',
            'aini@sigap.test',
            'hasan@sigap.test',
        ];
        $regionalSizes = ['small', 'medium', 'large'];

        Location::query()
            ->where('type', 'district')
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->each(function (Location $location, int $index) use (&$plans, $regionalFarmers, $regionalSizes, $weekStart): void {
                $volumeProfiles = [
                    [1800, 2200],
                    [3300, 3700],
                    [4600, 5000],
                ];
                $profile = $volumeProfiles[$index % count($volumeProfiles)];

                foreach ($profile as $sequence => $volume) {
                    $plans[] = [
                        'farmer' => $regionalFarmers[($index + $sequence) % count($regionalFarmers)],
                        'pond' => "Tambak Demo {$location->name} ".($sequence + 1),
                        'location' => $location->code,
                        'size' => $regionalSizes[($index + $sequence) % count($regionalSizes)],
                        'date' => $weekStart->addDays(($index + $sequence) % 7),
                        'volume' => $volume,
                        'price' => 29500 + (($index % 6) * 1000) + ($sequence * 500),
                        'latitude' => -7.0000000 + ($index * 0.0060000) + ($sequence * 0.0015000),
                        'longitude' => 112.4500000 + ($index * 0.0090000) + ($sequence * 0.0020000),
                        'status' => 'planned',
                    ];
                }
            });

        foreach ($plans as $plan) {
            $farmer = User::query()->where('email', $plan['farmer'])->firstOrFail();
            $location = Location::query()->where('code', $plan['location'])->firstOrFail();
            $fishSize = FishSize::query()
                ->where('commodity_id', $commodity->id)
                ->where('code', $plan['size'])
                ->firstOrFail();

            HarvestPlan::query()->withTrashed()->updateOrCreate(
                ['farmer_id' => $farmer->id, 'pond_name' => $plan['pond']],
                [
                    'location_id' => $location->id,
                    'commodity_id' => $commodity->id,
                    'fish_size_id' => $fishSize->id,
                    'harvest_date' => $plan['date'],
                    'estimated_volume_kg' => $plan['volume'],
                    'asking_price_per_kg' => $plan['price'],
                    'pond_address' => "Kecamatan {$location->name}, Kabupaten Gresik, Jawa Timur",
                    'latitude' => $plan['latitude'],
                    'longitude' => $plan['longitude'],
                    'status' => $plan['status'],
                    'notes' => 'Pasokan bandeng air payau berkualitas. Jadwal dan volume dapat didiskusikan melalui SIGAP.',
                    'deleted_at' => null,
                ],
            );
        }
    }
}
