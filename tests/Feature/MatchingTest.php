<?php

use App\Models\BuyerDemand;
use App\Models\Commodity;
use App\Models\FishSize;
use App\Models\HarvestPlan;
use App\Models\Location;
use App\Models\Reservation;
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

it('keeps a whole kilogram request when availability differs by one hundredth', function () {
    Carbon::setTestNow('2026-09-12');
    $fixture = matchingFixture();
    $fixture['demand']->update(['required_volume_kg' => 100]);
    $fixture['plan']->update(['estimated_volume_kg' => 100]);
    Reservation::query()->create([
        'harvest_plan_id' => $fixture['plan']->id,
        'buyer_id' => $fixture['buyer']->id,
        'reserved_volume_kg' => 0.01,
        'status' => 'confirmed',
    ]);

    $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/buyer-demands/{$fixture['demand']->id}/matches/generate")
        ->assertOk()
        ->assertJsonPath('data.0.matched_volume_kg', '100.00');

    $this->assertDatabaseHas('matches', [
        'buyer_demand_id' => $fixture['demand']->id,
        'matched_volume_kg' => 100,
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

it('returns buyer recommendations only for the signed in farmers plans', function () {
    Carbon::setTestNow('2026-09-12');
    $fixture = matchingFixture();
    $fixture['buyer']->update(['phone' => '0813-4567-8901']);
    $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/buyer-demands/{$fixture['demand']->id}/matches/generate")
        ->assertOk();

    $response = $this->actingAs($fixture['farmer'])
        ->getJson('/api/v1/farmer/matches')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.buyer.name', $fixture['buyer']->name)
        ->assertJsonPath('data.0.demand.required_volume_kg', '5000.00')
        ->assertJsonPath('data.0.harvest_plan.id', $fixture['plan']->id)
        ->assertJsonPath('data.0.buyer.whatsapp_url', fn ($url) => str_starts_with($url, 'https://wa.me/6281345678901?text='));

    expect($response->getContent())
        ->not->toContain($fixture['buyer']->phone)
        ->not->toContain($fixture['buyer']->email);

    $otherFarmer = User::factory()->create();
    $otherFarmer->roles()->attach(Role::query()->where('code', 'farmer')->first(), ['created_at' => now()]);
    $this->actingAs($otherFarmer)->getJson('/api/v1/farmer/matches')->assertOk()->assertJsonCount(0, 'data');
});

it('requires farmer role and validates farmer recommendation filters', function () {
    $fixture = matchingFixture();

    $this->getJson('/api/v1/farmer/matches')->assertUnauthorized();
    $this->actingAs($fixture['buyer'])->getJson('/api/v1/farmer/matches')->assertForbidden();
    $this->actingAs($fixture['farmer'])
        ->getJson('/api/v1/farmer/matches?harvest_plan_id=invalid&per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['harvest_plan_id', 'per_page']);
});

it('filters buyer recommendations by an owned harvest plan id', function () {
    Carbon::setTestNow('2026-09-12');
    $fixture = matchingFixture();
    $this->actingAs($fixture['buyer'])->postJson("/api/v1/buyer-demands/{$fixture['demand']->id}/matches/generate")->assertOk();

    $this->actingAs($fixture['farmer'])
        ->getJson("/api/v1/farmer/matches?harvest_plan_id={$fixture['plan']->id}")
        ->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($fixture['farmer'])
        ->getJson('/api/v1/farmer/matches?harvest_plan_id=999999')
        ->assertOk()->assertJsonCount(0, 'data');
});
