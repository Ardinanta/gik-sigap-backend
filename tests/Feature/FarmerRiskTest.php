<?php

use App\Models\Commodity;
use App\Models\FishSize;
use App\Models\HarvestPlan;
use App\Models\Location;
use App\Models\RiskThreshold;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function riskFixture(): array
{
    $farmerRole = Role::query()->create(['code' => 'farmer', 'name' => 'Petambak']);
    $buyerRole = Role::query()->create(['code' => 'buyer', 'name' => 'Pembeli']);
    $farmer = User::factory()->create();
    $farmer->roles()->attach($farmerRole, ['created_at' => now()]);
    $otherFarmer = User::factory()->create();
    $otherFarmer->roles()->attach($farmerRole, ['created_at' => now()]);
    $buyer = User::factory()->create();
    $buyer->roles()->attach($buyerRole, ['created_at' => now()]);
    $commodity = Commodity::query()->create(['code' => 'bandeng', 'name' => 'Bandeng', 'is_active' => true]);
    $size = FishSize::query()->create(['commodity_id' => $commodity->id, 'code' => 'medium', 'name' => 'Sedang', 'is_active' => true]);
    $location = Location::query()->create(['code' => 'MANYAR', 'name' => 'Manyar', 'type' => 'district', 'is_active' => true]);
    RiskThreshold::query()->create(['location_id' => $location->id, 'commodity_id' => $commodity->id, 'period_type' => 'weekly', 'threshold_volume_kg' => 8000, 'warning_ratio' => 0.8, 'effective_from' => '2026-01-01', 'source_note' => 'Threshold uji', 'is_active' => true]);

    $base = ['location_id' => $location->id, 'commodity_id' => $commodity->id, 'fish_size_id' => $size->id, 'harvest_date' => '2026-10-20', 'pond_name' => 'Tambak Uji', 'status' => 'planned'];
    HarvestPlan::query()->create([...$base, 'farmer_id' => $farmer->id, 'estimated_volume_kg' => 2500]);
    HarvestPlan::query()->create([...$base, 'farmer_id' => $otherFarmer->id, 'estimated_volume_kg' => 4500, 'pond_name' => 'Tambak Lain']);
    HarvestPlan::query()->create([...$base, 'farmer_id' => $otherFarmer->id, 'estimated_volume_kg' => 9000, 'harvest_date' => '2026-10-27', 'pond_name' => 'Minggu Lain']);

    return compact('farmer', 'buyer', 'location');
}

it('calculates the weekly regional risk and farmer contribution', function () {
    $fixture = riskFixture();

    $this->actingAs($fixture['farmer'])
        ->getJson('/api/v1/farmer/risks?week=2026-10-21')
        ->assertOk()
        ->assertJsonPath('data.period.start', '2026-10-19')
        ->assertJsonPath('data.period.end', '2026-10-25')
        ->assertJsonPath('data.summary.warning_count', 1)
        ->assertJsonPath('data.summary.high_count', 0)
        ->assertJsonPath('data.my_regions.0.location.name', 'Manyar')
        ->assertJsonPath('data.my_regions.0.total_volume_kg', '7000.00')
        ->assertJsonPath('data.my_regions.0.my_volume_kg', '2500.00')
        ->assertJsonPath('data.my_regions.0.other_volume_kg', '4500.00')
        ->assertJsonPath('data.my_regions.0.remaining_volume_kg', '1000.00')
        ->assertJsonPath('data.my_regions.0.utilization_percentage', 87.5)
        ->assertJsonPath('data.my_regions.0.risk_level', 'warning');
});

it('requires farmer role and validates the requested week', function () {
    $fixture = riskFixture();

    $this->getJson('/api/v1/farmer/risks')->assertUnauthorized();
    $this->actingAs($fixture['buyer'])->getJson('/api/v1/farmer/risks')->assertForbidden();
    $this->actingAs($fixture['farmer'])->getJson('/api/v1/farmer/risks?week=21-10-2026')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('week');
});

it('reports an unconfigured farmer region without inventing a threshold', function () {
    $fixture = riskFixture();
    RiskThreshold::query()->delete();

    $this->actingAs($fixture['farmer'])
        ->getJson('/api/v1/farmer/risks?week=2026-10-19')
        ->assertOk()
        ->assertJsonPath('data.summary.configured_region_count', 0)
        ->assertJsonPath('data.unconfigured_my_region_count', 1)
        ->assertJsonCount(0, 'data.regions')
        ->assertJsonCount(0, 'data.my_regions');
});
