<?php

use App\Models\Partnership;
use App\Models\Reservation;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets only the owning farmer read and confirm an incoming partnership', function () {
    $fixture = partnershipFixture();
    $partnership = Partnership::query()->create([
        'match_id' => $fixture['match']->id,
        'initiated_by_user_id' => $fixture['buyer']->id,
        'status' => 'interested',
    ]);

    $this->actingAs($fixture['buyer'])->getJson('/api/v1/farmer/partnerships')->assertForbidden();
    $response = $this->actingAs($fixture['farmer'])->getJson('/api/v1/farmer/partnerships')
        ->assertOk()->assertJsonPath('data.0.buyer.name', $fixture['buyer']->name);
    expect($response->getContent())->not->toContain($fixture['buyer']->email);

    $this->actingAs($fixture['farmer'])->postJson("/api/v1/farmer/partnerships/{$partnership->id}/confirmation")
        ->assertOk()->assertJsonPath('data.status', 'matched')
        ->assertJsonPath('data.agreed_volume_kg', '500.00');

    $this->actingAs($fixture['farmer'])->getJson('/api/v1/farmer/partnerships?view=active')
        ->assertOk()
        ->assertJsonPath('data.0.id', $partnership->id)
        ->assertJsonPath('data.0.status', 'matched')
        ->assertJsonPath('summary.active_count', 1)
        ->assertJsonPath('summary.pending_request_count', 0);

    $this->assertDatabaseHas('partnership_status_histories', [
        'partnership_id' => $partnership->id,
        'changed_by_user_id' => $fixture['farmer']->id,
        'from_status' => 'interested',
        'to_status' => 'matched',
    ]);

    $this->actingAs($fixture['farmer'])->postJson("/api/v1/farmer/partnerships/{$partnership->id}/confirmation")
        ->assertUnprocessable()->assertJsonValidationErrors('partnership');
});

it('moves a confirmed reservation into active partnerships for both parties', function () {
    $fixture = catalogFixture();
    $plan = createCatalogPlan($fixture);
    $reservation = Reservation::query()->create([
        'harvest_plan_id' => $plan->id,
        'buyer_id' => $fixture['buyer']->id,
        'reserved_volume_kg' => 100,
        'status' => 'pending',
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($fixture['farmer'])->getJson('/api/v1/farmer/reservations')
        ->assertOk()->assertJsonPath('data.0.buyer.name', $fixture['buyer']->name);

    $this->actingAs($fixture['farmer'])->postJson("/api/v1/farmer/reservations/{$reservation->id}/confirmation")
        ->assertOk()->assertJsonPath('data.status', 'confirmed');
    expect($reservation->fresh()->confirmed_at)->not->toBeNull();

    $partnership = Partnership::query()->where('reservation_id', $reservation->id)->sole();

    $farmerResponse = $this->actingAs($fixture['farmer'])
        ->getJson('/api/v1/farmer/partnerships?view=active')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $partnership->id)
        ->assertJsonPath('data.0.status', 'matched')
        ->assertJsonPath('data.0.buyer.name', $fixture['buyer']->name)
        ->assertJsonPath('data.0.supply.id', $plan->id)
        ->assertJsonPath('data.0.supply.pond_name', $plan->pond_name)
        ->assertJsonPath('data.0.agreed_volume_kg', '100.00')
        ->assertJsonPath('summary.active_count', 1)
        ->assertJsonPath('summary.agreed_volume_kg', '100.00');

    $buyerResponse = $this->actingAs($fixture['buyer'])
        ->getJson('/api/v1/partnerships?view=active')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $partnership->id)
        ->assertJsonPath('data.0.supply.id', $plan->id);

    foreach ([$farmerResponse, $buyerResponse] as $response) {
        expect($response->getContent())
            ->not->toContain('"phone"')
            ->not->toContain($fixture['buyer']->email);
    }

    $this->actingAs($fixture['farmer'])
        ->postJson("/api/v1/farmer/reservations/{$reservation->id}/confirmation")
        ->assertOk();
    expect(Partnership::query()->where('reservation_id', $reservation->id)->count())->toBe(1);
});

it('keeps pending reservations unallocated and rejects acceptance beyond remaining supply', function () {
    $fixture = catalogFixture();
    $plan = createCatalogPlan($fixture, ['estimated_volume_kg' => 1000]);
    $first = Reservation::query()->create([
        'harvest_plan_id' => $plan->id,
        'buyer_id' => $fixture['buyer']->id,
        'reserved_volume_kg' => 700,
        'status' => 'pending',
        'expires_at' => now()->addHour(),
    ]);
    $second = Reservation::query()->create([
        'harvest_plan_id' => $plan->id,
        'buyer_id' => $fixture['buyer']->id,
        'reserved_volume_kg' => 400,
        'status' => 'pending',
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($fixture['buyer'])
        ->getJson('/api/v1/catalog')
        ->assertOk()
        ->assertJsonPath('data.0.available_volume_kg', '1000.00')
        ->assertJsonPath('data.0.reserved_volume_kg', '0.00');

    $this->actingAs($fixture['farmer'])
        ->postJson("/api/v1/farmer/reservations/{$first->id}/confirmation")
        ->assertOk();

    $this->actingAs($fixture['buyer'])
        ->getJson('/api/v1/catalog')
        ->assertOk()
        ->assertJsonPath('data.0.available_volume_kg', '300.00')
        ->assertJsonPath('data.0.reserved_volume_kg', '700.00');

    $this->actingAs($fixture['farmer'])
        ->postJson("/api/v1/farmer/reservations/{$second->id}/confirmation")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reservation');

    expect($second->fresh()->status)->toBe('pending');
    expect(Partnership::query()->where('reservation_id', $second->id)->exists())->toBeFalse();
    expect(Partnership::query()->where('reservation_id', $first->id)->count())->toBe(1);

    $this->actingAs($fixture['buyer'])
        ->getJson('/api/v1/catalog')
        ->assertOk()
        ->assertJsonPath('data.0.available_volume_kg', '300.00');
});

it('returns only transactions sold by the farmer', function () {
    $fixture = partnershipFixture();
    $partnership = Partnership::query()->create([
        'match_id' => $fixture['match']->id,
        'initiated_by_user_id' => $fixture['buyer']->id,
        'status' => 'completed',
        'agreed_volume_kg' => 100,
        'agreed_price_per_kg' => 32000,
    ]);
    Transaction::query()->create([
        'partnership_id' => $partnership->id,
        'seller_id' => $fixture['farmer']->id,
        'buyer_id' => $fixture['buyer']->id,
        'location_id' => $fixture['location']->id,
        'commodity_id' => $fixture['commodity']->id,
        'fish_size_id' => $fixture['fishSize']->id,
        'volume_kg' => 100,
        'price_per_kg' => 32000,
        'transaction_date' => today(),
        'recorded_by' => $fixture['buyer']->id,
    ]);

    $this->actingAs($fixture['farmer'])->getJson('/api/v1/farmer/transactions')
        ->assertOk()->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.buyer_name', $fixture['buyer']->name);
});
