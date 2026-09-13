<?php

use App\Models\BuyerDemand;
use App\Models\MatchResult;
use App\Models\Partnership;
use App\Models\PartnershipStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function partnershipFixture(?array $fixture = null): array
{
    $fixture ??= catalogFixture();
    $plan = createCatalogPlan($fixture);
    $demand = BuyerDemand::query()->create([
        'buyer_id' => $fixture['buyer']->id,
        'commodity_id' => $fixture['commodity']->id,
        'fish_size_id' => $fixture['fishSize']->id,
        'required_volume_kg' => 500,
        'need_start_date' => '2026-09-18',
        'need_end_date' => '2026-09-25',
        'status' => 'active',
    ]);
    $match = MatchResult::query()->create([
        'harvest_plan_id' => $plan->id,
        'buyer_demand_id' => $demand->id,
        'match_score' => 90,
        'matched_volume_kg' => 500,
        'algorithm_version' => 'partnership-test',
        'status' => 'recommended',
    ]);

    return [...$fixture, 'plan' => $plan, 'demand' => $demand, 'match' => $match];
}

it('requires authentication and buyer role for partnership endpoints', function () {
    $fixture = partnershipFixture();

    $this->getJson('/api/v1/partnerships')->assertUnauthorized();
    $this->postJson("/api/v1/matches/{$fixture['match']->id}/partnership")->assertUnauthorized();
    $this->actingAs($fixture['farmer'])->getJson('/api/v1/partnerships')->assertForbidden();
    $this->actingAs($fixture['farmer'])->postJson("/api/v1/matches/{$fixture['match']->id}/partnership")->assertForbidden();
});

it('creates partnership, initial history, and accepts match atomically', function () {
    $fixture = partnershipFixture();

    $response = $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/matches/{$fixture['match']->id}/partnership", [
            'status' => 'matched',
            'initiated_by_user_id' => $fixture['farmer']->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'interested')
        ->assertJsonPath('data.supply.farmer_name', 'Budi Tambak');

    $partnershipId = $response->json('data.id');
    $this->assertDatabaseHas('partnerships', [
        'id' => $partnershipId,
        'match_id' => $fixture['match']->id,
        'initiated_by_user_id' => $fixture['buyer']->id,
        'status' => 'interested',
    ]);
    $this->assertDatabaseHas('partnership_status_histories', [
        'partnership_id' => $partnershipId,
        'changed_by_user_id' => $fixture['buyer']->id,
        'from_status' => null,
        'to_status' => 'interested',
    ]);
    $this->assertDatabaseHas('matches', ['id' => $fixture['match']->id, 'status' => 'accepted']);
    expect($response->getContent())->not->toContain($fixture['farmer']->phone);
});

it('hides another buyers match and partnership with not found', function () {
    $fixture = partnershipFixture();
    $otherBuyer = User::factory()->create();
    $otherBuyer->roles()->attach($fixture['buyer']->roles->first()->id, ['created_at' => now()]);

    $this->actingAs($otherBuyer)
        ->postJson("/api/v1/matches/{$fixture['match']->id}/partnership")
        ->assertNotFound();

    $partnership = Partnership::query()->create([
        'match_id' => $fixture['match']->id,
        'initiated_by_user_id' => $fixture['buyer']->id,
        'status' => 'interested',
    ]);

    $this->actingAs($otherBuyer)->getJson("/api/v1/partnerships/{$partnership->id}")->assertNotFound();
    $this->actingAs($otherBuyer)->getJson("/api/v1/partnerships/{$partnership->id}/history")->assertNotFound();
});

it('rejects duplicate and invalid partnership creation', function () {
    $fixture = partnershipFixture();

    $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/matches/{$fixture['match']->id}/partnership")
        ->assertCreated();
    $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/matches/{$fixture['match']->id}/partnership")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('match');

    $another = partnershipFixture($fixture);
    $another['match']->update(['status' => 'expired']);
    $this->actingAs($another['buyer'])
        ->postJson("/api/v1/matches/{$another['match']->id}/partnership")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('match');
});

it('filters buyer partnerships and returns history without pii', function () {
    $fixture = partnershipFixture();
    $partnership = Partnership::query()->create([
        'match_id' => $fixture['match']->id,
        'initiated_by_user_id' => $fixture['buyer']->id,
        'status' => 'matched',
        'agreed_volume_kg' => 250,
        'agreed_price_per_kg' => 32000,
    ]);
    PartnershipStatusHistory::query()->create([
        'partnership_id' => $partnership->id,
        'changed_by_user_id' => $fixture['buyer']->id,
        'from_status' => 'discussing',
        'to_status' => 'matched',
        'changed_at' => now(),
    ]);

    $response = $this->actingAs($fixture['buyer'])
        ->getJson('/api/v1/partnerships?view=active')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('summary.active_count', 1)
        ->assertJsonPath('summary.agreed_volume_kg', '250.00')
        ->assertJsonPath('data.0.id', $partnership->id);

    expect($response->getContent())->not->toContain($fixture['farmer']->phone);

    $this->actingAs($fixture['buyer'])
        ->getJson("/api/v1/partnerships/{$partnership->id}/history")
        ->assertOk()
        ->assertJsonPath('data.0.to_status', 'matched');
});
