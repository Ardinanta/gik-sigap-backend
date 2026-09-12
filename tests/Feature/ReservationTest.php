<?php

use App\Models\BuyerDemand;
use App\Models\MatchResult;
use App\Models\Partnership;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication and buyer role to reserve supply', function () {
    $fixture = catalogFixture();
    $plan = createCatalogPlan($fixture);

    $this->postJson("/api/v1/harvest-plans/{$plan->id}/reservations", ['volume_kg' => 100])->assertUnauthorized();
    $this->actingAs($fixture['farmer'])
        ->postJson("/api/v1/harvest-plans/{$plan->id}/reservations", ['volume_kg' => 100])
        ->assertForbidden();
});

it('creates a server controlled pending reservation with expiry and notes', function () {
    $fixture = catalogFixture();
    $plan = createCatalogPlan($fixture);

    $response = $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/harvest-plans/{$plan->id}/reservations", [
            'volume_kg' => 250,
            'notes' => 'Armada tiba pukul 06.00 WIB.',
            'buyer_id' => $fixture['farmer']->id,
            'status' => 'confirmed',
            'expires_at' => now()->addYear(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.reserved_volume_kg', '250.00')
        ->assertJsonPath('data.notes', 'Armada tiba pukul 06.00 WIB.');

    $this->assertDatabaseHas('reservations', [
        'harvest_plan_id' => $plan->id,
        'buyer_id' => $fixture['buyer']->id,
        'buyer_demand_id' => null,
        'reserved_volume_kg' => 250,
        'status' => 'pending',
    ]);
    expect($response->json('data.expires_at'))->not->toBeNull();
    expect($response->json('data'))->not->toHaveKeys(['buyer_id', 'phone', 'email']);
});

it('validates reservation payload', function () {
    $fixture = catalogFixture();
    $plan = createCatalogPlan($fixture);

    $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/harvest-plans/{$plan->id}/reservations", [
            'volume_kg' => 0,
            'notes' => str_repeat('a', 1001),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['volume_kg', 'notes']);
});

it('uses the latest available volume and rejects over reservation', function () {
    $fixture = catalogFixture();
    $plan = createCatalogPlan($fixture);
    $demand = BuyerDemand::query()->create([
        'buyer_id' => $fixture['buyer']->id,
        'commodity_id' => $fixture['commodity']->id,
        'fish_size_id' => $fixture['fishSize']->id,
        'required_volume_kg' => 1000,
        'need_start_date' => '2026-09-18',
        'need_end_date' => '2026-09-25',
        'status' => 'active',
    ]);
    $match = MatchResult::query()->create([
        'harvest_plan_id' => $plan->id,
        'buyer_demand_id' => $demand->id,
        'match_score' => 90,
        'algorithm_version' => 'reservation-test',
        'status' => 'accepted',
    ]);
    Partnership::query()->create([
        'match_id' => $match->id,
        'initiated_by_user_id' => $fixture['buyer']->id,
        'status' => 'matched',
        'agreed_volume_kg' => 300,
    ]);
    Reservation::query()->create([
        'harvest_plan_id' => $plan->id,
        'buyer_id' => $fixture['buyer']->id,
        'reserved_volume_kg' => 200,
        'status' => 'confirmed',
    ]);
    Reservation::query()->create([
        'harvest_plan_id' => $plan->id,
        'buyer_id' => $fixture['buyer']->id,
        'reserved_volume_kg' => 100,
        'status' => 'pending',
        'expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/harvest-plans/{$plan->id}/reservations", ['volume_kg' => 501])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['volume_kg']);

    $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/harvest-plans/{$plan->id}/reservations", ['volume_kg' => 500])
        ->assertCreated();
});

it('rejects reservations for unavailable plans', function (array $attributes) {
    $fixture = catalogFixture();
    $plan = createCatalogPlan($fixture, $attributes);

    $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/harvest-plans/{$plan->id}/reservations", ['volume_kg' => 100])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['volume_kg']);
})->with([
    'completed' => [['status' => 'completed']],
    'past harvest' => [['harvest_date' => '2026-09-11']],
]);
