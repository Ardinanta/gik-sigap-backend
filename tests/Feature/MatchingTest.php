<?php

use App\Models\BuyerDemand;
use App\Models\Commodity;
use App\Models\FishSize;
use App\Models\HarvestPlan;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function matchingFixture(): array
{
    $buyerRole = Role::query()->create(['code' => 'buyer', 'name' => 'Pembeli']);
    $farmerRole = Role::query()->create(['code' => 'farmer', 'name' => 'Petambak']);
    $buyer = User::factory()->create();
    $buyer->roles()->attach($buyerRole, ['created_at' => now()]);
    $farmer = User::factory()->create(['phone' => '081234567890']);
    $farmer->roles()->attach($farmerRole, ['created_at' => now()]);
    $commodity = Commodity::query()->create(['code' => 'bandeng', 'name' => 'Bandeng', 'is_active' => true]);
    $size = FishSize::query()->create(['commodity_id' => $commodity->id, 'code' => 'medium', 'name' => 'Sedang', 'is_active' => true]);
    $location = Location::query()->create(['code' => 'MANYAR', 'name' => 'Manyar', 'type' => 'district', 'is_active' => true]);
    $demand = BuyerDemand::query()->create([
        'buyer_id' => $buyer->id,
        'target_location_id' => $location->id,
        'commodity_id' => $commodity->id,
        'fish_size_id' => $size->id,
        'required_volume_kg' => 5000,
        'need_start_date' => '2026-09-15',
        'need_end_date' => '2026-09-30',
        'status' => 'active',
    ]);
    $plan = HarvestPlan::query()->create([
        'farmer_id' => $farmer->id,
        'location_id' => $location->id,
        'commodity_id' => $commodity->id,
        'fish_size_id' => $size->id,
        'harvest_date' => '2026-09-20',
        'estimated_volume_kg' => 2500,
        'asking_price_per_kg' => 32000,
        'pond_name' => 'Tambak Cocok',
        'status' => 'planned',
    ]);

    return compact('buyer', 'farmer', 'demand', 'plan');
}

it('requires a buyer and protects demand ownership', function () {
    Carbon::setTestNow('2026-09-12');
    $fixture = matchingFixture();
    $other = User::factory()->create();
    $other->roles()->attach(Role::query()->where('code', 'buyer')->first(), ['created_at' => now()]);

    $this->getJson("/api/v1/buyer-demands/{$fixture['demand']->id}/matches")->assertUnauthorized();
    $this->actingAs($fixture['farmer'])->postJson("/api/v1/buyer-demands/{$fixture['demand']->id}/matches/generate")->assertForbidden();
    $this->actingAs($other)->getJson("/api/v1/buyer-demands/{$fixture['demand']->id}/matches")->assertNotFound();
});

it('generates ranked recommendations with score breakdown and no raw pii', function () {
    Carbon::setTestNow('2026-09-12');
    $fixture = matchingFixture();

    $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/buyer-demands/{$fixture['demand']->id}/matches/generate")
        ->assertOk()
        ->assertJsonPath('data.0.match_score', '92.50')
        ->assertJsonPath('data.0.score_breakdown.size', 40)
        ->assertJsonPath('data.0.score_breakdown.location', 25)
        ->assertJsonPath('data.0.score_breakdown.period', 20)
        ->assertJsonPath('data.0.score_breakdown.volume', 7.5)
        ->assertJsonPath('data.0.score_category', 'Sangat Cocok')
        ->assertJsonPath('data.0.supply.pond_name', 'Tambak Cocok')
        ->assertJsonMissingPath('data.0.supply.phone')
        ->assertJsonMissingPath('data.0.supply.email')
        ->assertJsonPath('data.0.supply.whatsapp_url', fn ($url) => str_starts_with($url, 'https://wa.me/'));

    $this->assertDatabaseHas('matches', [
        'buyer_demand_id' => $fixture['demand']->id,
        'harvest_plan_id' => $fixture['plan']->id,
        'status' => 'recommended',
        'algorithm_version' => 'v1',
    ]);
});

it('uses size and demand period as hard filters', function () {
    Carbon::setTestNow('2026-09-12');
    $fixture = matchingFixture();

    $otherSize = FishSize::query()->create([
        'commodity_id' => $fixture['plan']->commodity_id,
        'code' => 'large',
        'name' => 'Besar',
        'is_active' => true,
    ]);

    foreach ([
        ['pond_name' => 'Ukuran Salah', 'fish_size_id' => $otherSize->id, 'harvest_date' => '2026-09-20'],
        ['pond_name' => 'Periode Salah', 'fish_size_id' => $fixture['plan']->fish_size_id, 'harvest_date' => '2026-10-10'],
    ] as $candidate) {
        HarvestPlan::query()->create([
            'farmer_id' => $fixture['farmer']->id,
            'location_id' => $fixture['plan']->location_id,
            'commodity_id' => $fixture['plan']->commodity_id,
            'fish_size_id' => $candidate['fish_size_id'],
            'harvest_date' => $candidate['harvest_date'],
            'estimated_volume_kg' => 5000,
            'pond_name' => $candidate['pond_name'],
            'status' => 'planned',
        ]);
    }

    $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/buyer-demands/{$fixture['demand']->id}/matches/generate")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.supply.pond_name', 'Tambak Cocok');
});

it('expires stale recommendations after the supply becomes unavailable', function () {
    Carbon::setTestNow('2026-09-12');
    $fixture = matchingFixture();
    $url = "/api/v1/buyer-demands/{$fixture['demand']->id}/matches/generate";

    $this->actingAs($fixture['buyer'])->postJson($url)->assertOk()->assertJsonCount(1, 'data');
    $fixture['plan']->update(['status' => 'cancelled']);
    $this->actingAs($fixture['buyer'])->postJson($url)->assertOk()->assertJsonCount(0, 'data');

    $this->assertDatabaseHas('matches', [
        'buyer_demand_id' => $fixture['demand']->id,
        'status' => 'expired',
    ]);
});

it('rejects generation for an inactive demand', function () {
    Carbon::setTestNow('2026-09-12');
    $fixture = matchingFixture();
    $fixture['demand']->update(['status' => 'cancelled']);

    $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/buyer-demands/{$fixture['demand']->id}/matches/generate")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('demand');
});
