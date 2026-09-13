<?php

namespace Database\Seeders;

use App\Models\Commodity;
use App\Models\Location;
use App\Models\RiskThreshold;
use Illuminate\Database\Seeder;

class RiskThresholdSeeder extends Seeder
{
    public function run(): void
    {
        $bandeng = Commodity::query()->where('code', 'bandeng')->where('is_active', true)->firstOrFail();

        Location::query()
            ->where('type', 'district')
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (Location $location) use ($bandeng): void {
                RiskThreshold::query()->firstOrCreate(
                    [
                        'location_id' => $location->id,
                        'commodity_id' => $bandeng->id,
                        'period_type' => 'weekly',
                        'is_active' => true,
                    ],
                    [
                        'threshold_volume_kg' => 8000,
                        'warning_ratio' => 0.8,
                        'effective_from' => '2026-01-01',
                        'effective_until' => null,
                        'source_note' => 'Baseline demo SIGAP; perlu divalidasi bersama pemangku kepentingan per kecamatan.',
                    ],
                );
            });
    }
}
