<?php

use App\Models\Commodity;
use App\Models\Location;
use App\Models\RiskThreshold;
use Database\Seeders\CommoditySeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RiskThresholdSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds an idempotent weekly baseline for every active district', function () {
    $this->seed([LocationSeeder::class, CommoditySeeder::class]);

    $this->seed(RiskThresholdSeeder::class);
    $this->seed(RiskThresholdSeeder::class);

    $districtCount = Location::query()->where('type', 'district')->where('is_active', true)->count();
    expect(RiskThreshold::query()->count())->toBe($districtCount);
    $this->assertDatabaseHas('risk_thresholds', [
        'commodity_id' => Commodity::query()->where('code', 'bandeng')->value('id'),
        'threshold_volume_kg' => 8000,
        'warning_ratio' => 0.8,
        'effective_from' => '2026-01-01',
        'is_active' => true,
    ]);
});

it('does not overwrite an existing active district threshold', function () {
    $this->seed([LocationSeeder::class, CommoditySeeder::class]);
    $location = Location::query()->where('type', 'district')->firstOrFail();
    $commodity = Commodity::query()->where('code', 'bandeng')->firstOrFail();
    RiskThreshold::query()->create([
        'location_id' => $location->id,
        'commodity_id' => $commodity->id,
        'period_type' => 'weekly',
        'threshold_volume_kg' => 12500,
        'warning_ratio' => 0.75,
        'effective_from' => '2026-01-01',
        'source_note' => 'Nilai khusus yang sudah ada.',
        'is_active' => true,
    ]);

    $this->seed(RiskThresholdSeeder::class);

    expect(RiskThreshold::query()->where('location_id', $location->id)->count())->toBe(1);
    $this->assertDatabaseHas('risk_thresholds', [
        'location_id' => $location->id,
        'threshold_volume_kg' => 12500,
        'warning_ratio' => 0.75,
        'source_note' => 'Nilai khusus yang sudah ada.',
    ]);
});
