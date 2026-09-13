<?php

use App\Models\BuyerDemand;
use App\Models\MatchResult;
use App\Models\Partnership;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns only buyer transaction snapshots and hides pii', function () {
    $fixture = catalogFixture();
    $plan = createCatalogPlan($fixture);
    $demand = BuyerDemand::query()->create([
        'buyer_id' => $fixture['buyer']->id,
        'commodity_id' => $fixture['commodity']->id,
        'fish_size_id' => $fixture['fishSize']->id,
        'required_volume_kg' => 100,
        'need_start_date' => '2026-09-18',
        'need_end_date' => '2026-09-25',
        'status' => 'fulfilled',
    ]);
    $match = MatchResult::query()->create([
        'harvest_plan_id' => $plan->id,
        'buyer_demand_id' => $demand->id,
        'match_score' => 90,
        'algorithm_version' => 'transaction-test',
        'status' => 'accepted',
    ]);
    $partnership = Partnership::query()->create([
        'match_id' => $match->id,
        'initiated_by_user_id' => $fixture['buyer']->id,
        'status' => 'completed',
        'agreed_volume_kg' => 100,
        'agreed_price_per_kg' => 32000,
    ]);
    $transaction = Transaction::query()->create([
        'partnership_id' => $partnership->id,
        'seller_id' => $fixture['farmer']->id,
        'buyer_id' => $fixture['buyer']->id,
        'location_id' => $fixture['location']->id,
        'commodity_id' => $fixture['commodity']->id,
        'fish_size_id' => $fixture['fishSize']->id,
        'volume_kg' => 100,
        'price_per_kg' => 32000,
        'transaction_date' => '2026-09-20',
        'recorded_by' => $fixture['buyer']->id,
        'locked_at' => now(),
    ]);
    $otherBuyer = User::factory()->create();
    $otherBuyer->roles()->attach($fixture['buyer']->roles->first()->id, ['created_at' => now()]);

    $response = $this->actingAs($fixture['buyer'])
        ->getJson('/api/v1/transactions')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $transaction->id)
        ->assertJsonPath('data.0.total_value', '3200000.00');

    expect($response->getContent())->not->toContain($fixture['farmer']->phone);
    $this->actingAs($otherBuyer)->getJson("/api/v1/transactions/{$transaction->id}")->assertNotFound();
});
