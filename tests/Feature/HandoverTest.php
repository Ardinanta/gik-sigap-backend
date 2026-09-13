<?php

use App\Models\BuyerDemand;
use App\Models\Commodity;
use App\Models\FishSize;
use App\Models\HarvestPlan;
use App\Models\Location;
use App\Models\MatchResult;
use App\Models\Partnership;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\PartnershipPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function handoverFixture(): array
{
    $buyer = User::factory()->create();
    $farmer = User::factory()->create();
    $buyer->roles()->attach(Role::query()->create(['code' => 'buyer', 'name' => 'Pembeli']));
    $farmer->roles()->attach(Role::query()->create(['code' => 'farmer', 'name' => 'Petambak']));
    $commodity = Commodity::query()->create(['code' => 'bandeng', 'name' => 'Bandeng', 'is_active' => true]);
    $size = FishSize::query()->create(['commodity_id' => $commodity->id, 'code' => 'medium', 'name' => 'Sedang', 'min_weight_gram' => 250, 'is_active' => true]);
    $location = Location::query()->create(['code' => 'MANYAR', 'name' => 'Manyar', 'type' => 'district', 'is_active' => true]);
    $plan = HarvestPlan::query()->create(['farmer_id' => $farmer->id, 'commodity_id' => $commodity->id, 'fish_size_id' => $size->id, 'location_id' => $location->id, 'harvest_date' => '2026-09-13', 'estimated_volume_kg' => 1000, 'pond_name' => 'Tambak Uji', 'status' => 'planned']);
    $demand = BuyerDemand::query()->create(['buyer_id' => $buyer->id, 'commodity_id' => $commodity->id, 'fish_size_id' => $size->id, 'required_volume_kg' => 500, 'need_start_date' => '2026-09-13', 'need_end_date' => '2026-09-20', 'status' => 'active']);
    $match = MatchResult::query()->create(['harvest_plan_id' => $plan->id, 'buyer_demand_id' => $demand->id, 'match_score' => 90, 'matched_volume_kg' => 500, 'algorithm_version' => 'v1', 'status' => 'accepted']);
    $partnership = Partnership::query()->create(['match_id' => $match->id, 'initiated_by_user_id' => $buyer->id, 'status' => 'matched', 'agreed_volume_kg' => 500, 'agreed_price_per_kg' => '31500.25']);

    return compact('buyer', 'farmer', 'partnership', 'plan');
}

it('completes only after equal weights and locks one transaction even on retries', function () {
    $this->travelTo(new DateTimeImmutable('2026-09-13 10:00:00+07:00'));
    $f = handoverFixture();
    $url = "/api/v1/partnerships/{$f['partnership']->id}/handover";
    $this->actingAs($f['farmer'])->postJson($url, ['volume_kg' => '498.25', 'version' => 0])
        ->assertOk()->assertJsonPath('data.status', 'matched')->assertJsonPath('data.handover.version', 1);
    $this->assertDatabaseCount('transactions', 0);

    $this->actingAs($f['buyer'])->postJson($url, ['volume_kg' => '498.25', 'version' => 1, 'price_per_kg' => 1, 'seller_weight_kg' => 999])
        ->assertOk()->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.transaction.volume_kg', '498.25')
        ->assertJsonPath('data.transaction.total_value', '15694999.56')
        ->assertJsonPath('data.transaction.transaction_date', '2026-09-13');
    $this->actingAs($f['buyer'])->postJson($url, ['volume_kg' => '498.25', 'version' => 1])->assertOk();
    $this->actingAs($f['buyer'])->postJson($url, ['volume_kg' => 400, 'version' => 2])->assertUnprocessable();

    $this->assertDatabaseCount('transactions', 1);
    $this->assertDatabaseCount('partnership_status_histories', 2);
    $this->assertDatabaseHas('transactions', ['partnership_id' => $f['partnership']->id, 'volume_kg' => '498.25', 'price_per_kg' => '31500.25', 'recorded_by' => $f['buyer']->id]);
    expect(Transaction::query()->first()->locked_at)->not->toBeNull();
});

it('supports reservation partnerships for both owners without exposing contact pii', function () {
    $this->travelTo(new DateTimeImmutable('2026-09-13 10:00:00+07:00'));
    $f = handoverFixture();
    $reservation = Reservation::query()->create([
        'harvest_plan_id' => $f['plan']->id,
        'buyer_id' => $f['buyer']->id,
        'reserved_volume_kg' => 300,
        'status' => 'confirmed',
        'confirmed_at' => now(),
    ]);
    $partnership = Partnership::query()->create([
        'reservation_id' => $reservation->id,
        'initiated_by_user_id' => $f['buyer']->id,
        'status' => 'matched',
        'agreed_volume_kg' => 300,
        'agreed_price_per_kg' => '31500.25',
    ]);
    $url = "/api/v1/partnerships/{$partnership->id}/handover";

    $farmerResponse = $this->actingAs($f['farmer'])->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.supply.id', $f['plan']->id)
        ->assertJsonPath('data.buyer.id', $f['buyer']->id);
    $buyerResponse = $this->actingAs($f['buyer'])->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.supply.id', $f['plan']->id);

    foreach ([$farmerResponse, $buyerResponse] as $response) {
        expect($response->getContent())->not->toContain('"phone"');
        expect($response->getContent())->not->toContain($f['buyer']->email);
    }

    $this->actingAs($f['farmer'])->postJson($url, ['volume_kg' => 298, 'version' => 0])
        ->assertOk()->assertJsonPath('data.status', 'matched');
    $this->actingAs($f['buyer'])->postJson($url, ['volume_kg' => 298, 'version' => 1])
        ->assertOk()->assertJsonPath('data.status', 'completed');

    $this->assertDatabaseCount('transactions', 1);
    $this->assertDatabaseHas('transactions', [
        'partnership_id' => $partnership->id,
        'seller_id' => $f['farmer']->id,
        'buyer_id' => $f['buyer']->id,
        'volume_kg' => 298,
    ]);

    $other = User::factory()->create();
    $other->roles()->attach($f['buyer']->roles()->first());
    $this->actingAs($other)->getJson($url)->assertNotFound();
});

it('keeps different weights pending and lets a party correct its own weight', function () {
    $f = handoverFixture();
    $url = "/api/v1/partnerships/{$f['partnership']->id}/handover";
    $this->actingAs($f['buyer'])->postJson($url, ['volume_kg' => 490, 'version' => 0])->assertOk();
    $this->actingAs($f['farmer'])->postJson($url, ['volume_kg' => 500, 'version' => 1])
        ->assertOk()->assertJsonPath('data.status', 'matched');
    $this->assertDatabaseCount('transactions', 0);

    $this->actingAs($f['farmer'])->postJson($url, ['volume_kg' => 490, 'version' => 2])
        ->assertOk()->assertJsonPath('data.status', 'completed');
    $this->assertDatabaseHas('transactions', ['volume_kg' => 490]);
});

it('returns 409 for stale confirmation without overwriting current weights', function () {
    $f = handoverFixture();
    $url = "/api/v1/partnerships/{$f['partnership']->id}/handover";
    $this->actingAs($f['farmer'])->postJson($url, ['volume_kg' => 500, 'version' => 0])->assertOk();
    $this->actingAs($f['buyer'])->postJson($url, ['volume_kg' => 500, 'version' => 0])->assertConflict();
    $this->assertDatabaseHas('partnerships', ['id' => $f['partnership']->id, 'buyer_weight_kg' => null, 'handover_version' => 1]);
    $this->assertDatabaseCount('transactions', 0);
});

it('requires authentication and hides handover from other accounts', function () {
    $f = handoverFixture();
    $url = "/api/v1/partnerships/{$f['partnership']->id}/handover";
    $this->getJson($url)->assertUnauthorized();
    $this->postJson($url, ['volume_kg' => 500, 'version' => 0])->assertUnauthorized();
    $other = User::factory()->create();
    $other->roles()->attach($f['buyer']->roles()->first());
    $this->actingAs($other)->getJson($url)->assertNotFound();
    $this->actingAs($other)->postJson($url, ['volume_kg' => 500, 'version' => 0])->assertNotFound();
    $this->assertDatabaseCount('partnership_status_histories', 0);
    $this->assertDatabaseCount('transactions', 0);
});

it('rejects invalid weight without recording a confirmation', function ($weight) {
    $f = handoverFixture();
    $this->actingAs($f['buyer'])->postJson("/api/v1/partnerships/{$f['partnership']->id}/handover", ['volume_kg' => $weight, 'version' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors('volume_kg');
    $this->assertDatabaseCount('partnership_status_histories', 0);
})->with(['missing' => [null], 'zero' => [0], 'negative' => [-1], 'precision' => ['1.001'], 'overflow' => ['10000000000'], 'text' => ['abc']]);

it('authorizes only the buyer and farmer with their corresponding roles', function () {
    $f = handoverFixture();
    $policy = new PartnershipPolicy;
    expect($policy->handover($f['buyer'], $f['partnership'])->allowed())->toBeTrue();
    expect($policy->handover($f['farmer'], $f['partnership'])->allowed())->toBeTrue();
    $outsider = User::factory()->create();
    $outsider->roles()->attach($f['farmer']->roles()->first());
    expect($policy->handover($outsider, $f['partnership'])->allowed())->toBeFalse();
    $f['buyer']->roles()->detach();
    $f['buyer']->unsetRelation('roles');
    expect($policy->handover($f['buyer'], $f['partnership'])->allowed())->toBeFalse();
    $f['farmer']->roles()->detach();
    $f['farmer']->unsetRelation('roles');
    expect($policy->handover($f['farmer'], $f['partnership'])->allowed())->toBeFalse();
});

it('requires a version and returns the persisted confirmation to both parties', function () {
    $f = handoverFixture();
    $url = "/api/v1/partnerships/{$f['partnership']->id}/handover";
    $this->actingAs($f['farmer'])->postJson($url, ['volume_kg' => 500])->assertUnprocessable()->assertJsonValidationErrors('version');
    $this->actingAs($f['farmer'])->postJson($url, ['volume_kg' => 500, 'version' => 0])->assertOk();
    $this->actingAs($f['buyer'])->getJson($url)->assertOk()
        ->assertJsonPath('data.handover.seller_weight_kg', '500.00')
        ->assertJsonPath('data.handover.buyer_weight_kg', null)
        ->assertJsonPath('data.buyer.id', $f['buyer']->id);
});

it('rejects handover before agreement or without an agreed price', function ($status, $price) {
    $f = handoverFixture();
    $f['partnership']->update(['status' => $status, 'agreed_price_per_kg' => $price]);
    $this->actingAs($f['buyer'])->postJson("/api/v1/partnerships/{$f['partnership']->id}/handover", ['volume_kg' => 500, 'version' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors('volume_kg');
    $this->assertDatabaseCount('transactions', 0);
})->with([['interested', 31500], ['cancelled', 31500], ['matched', null]]);

it('allows handover only when the harvest date has arrived', function () {
    $this->travelTo(new DateTimeImmutable('2026-09-12 10:00:00+07:00'));
    $f = handoverFixture();
    $url = "/api/v1/partnerships/{$f['partnership']->id}/handover";

    $this->actingAs($f['buyer'])->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.handover.can_confirm', false)
        ->assertJsonPath('data.handover.unavailable_reason', 'Konfirmasi tersedia mulai tanggal panen.');
    $this->actingAs($f['buyer'])->postJson($url, ['volume_kg' => 500, 'version' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('volume_kg');
    $this->assertDatabaseHas('partnerships', ['id' => $f['partnership']->id, 'buyer_weight_kg' => null, 'handover_version' => 0]);

    $this->travelTo(new DateTimeImmutable('2026-09-13 07:00:00+07:00'));
    $this->actingAs($f['buyer'])->postJson($url, ['volume_kg' => 500, 'version' => 0])
        ->assertOk()
        ->assertJsonPath('data.handover.buyer_weight_kg', '500.00');
});

it('rolls back confirmation and status if recording the transaction fails', function () {
    $f = handoverFixture();
    $url = "/api/v1/partnerships/{$f['partnership']->id}/handover";
    $this->actingAs($f['farmer'])->postJson($url, ['volume_kg' => 500, 'version' => 0])->assertOk();
    Transaction::creating(function (): void {
        throw new RuntimeException('Simulated transaction failure');
    });
    try {
        $this->actingAs($f['buyer'])->postJson($url, ['volume_kg' => 500, 'version' => 1])->assertStatus(500);
    } finally {
        Transaction::flushEventListeners();
    }
    $this->assertDatabaseHas('partnerships', ['id' => $f['partnership']->id, 'status' => 'matched', 'buyer_weight_kg' => null, 'handover_version' => 1]);
    $this->assertDatabaseCount('transactions', 0);
    $this->assertDatabaseCount('partnership_status_histories', 1);
});
