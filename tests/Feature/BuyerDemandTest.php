<?php

use App\Models\BuyerDemand;
use App\Models\Commodity;
use App\Models\FishSize;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function buyerDemandFixture(): array
{
    $buyerRole = Role::query()->create(['code' => 'buyer', 'name' => 'Pembeli']);
    $farmerRole = Role::query()->create(['code' => 'farmer', 'name' => 'Petambak']);
    $buyer = User::factory()->create();
    $buyer->roles()->attach($buyerRole->id, ['created_at' => now()]);
    $farmer = User::factory()->create();
    $farmer->roles()->attach($farmerRole->id, ['created_at' => now()]);
    $commodity = Commodity::query()->create(['code' => 'bandeng', 'name' => 'Bandeng', 'is_active' => true]);
    $fishSize = FishSize::query()->create([
        'commodity_id' => $commodity->id,
        'code' => 'medium',
        'name' => 'Sedang',
        'min_weight_gram' => 250,
        'max_weight_gram' => 333.33,
        'is_active' => true,
    ]);
    $location = Location::query()->create([
        'code' => 'MANYAR',
        'name' => 'Manyar',
        'type' => 'district',
        'is_active' => true,
    ]);

    return compact('buyer', 'farmer', 'commodity', 'fishSize', 'location');
}

function validDemandPayload(array $fixture): array
{
    return [
        'fish_size_id' => $fixture['fishSize']->id,
        'required_volume_kg' => 5000,
        'target_location_id' => $fixture['location']->id,
        'need_start_date' => '2026-09-20',
        'need_end_date' => '2026-09-27',
        'notes' => 'Siap ditimbang di kolam.',
    ];
}

it('requires authentication and the buyer role', function () {
    Carbon::setTestNow('2026-09-12');
    $fixture = buyerDemandFixture();
    $payload = validDemandPayload($fixture);

    $this->getJson('/api/v1/buyer-demands')->assertUnauthorized();
    $this->postJson('/api/v1/buyer-demands', $payload)->assertUnauthorized();

    $this->actingAs($fixture['farmer'])
        ->postJson('/api/v1/buyer-demands', $payload)
        ->assertForbidden();

    $this->assertDatabaseCount('buyer_demands', 0);
});

it('creates a demand with server-controlled ownership commodity and status', function () {
    Carbon::setTestNow('2026-09-12');
    $fixture = buyerDemandFixture();
    $payload = [
        ...validDemandPayload($fixture),
        'buyer_id' => $fixture['farmer']->id,
        'commodity_id' => 999,
        'status' => 'fulfilled',
    ];

    $this->actingAs($fixture['buyer'])
        ->postJson('/api/v1/buyer-demands', $payload)
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.fish_size.name', 'Sedang')
        ->assertJsonPath('data.target_location.name', 'Manyar')
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('buyer_demands', [
        'buyer_id' => $fixture['buyer']->id,
        'commodity_id' => $fixture['commodity']->id,
        'status' => 'active',
        'required_volume_kg' => 5000,
    ]);
});

it('accepts all districts when target location is null', function () {
    Carbon::setTestNow('2026-09-12');
    $fixture = buyerDemandFixture();
    $payload = validDemandPayload($fixture);
    $payload['target_location_id'] = null;

    $this->actingAs($fixture['buyer'])
        ->postJson('/api/v1/buyer-demands', $payload)
        ->assertCreated()
        ->assertJsonPath('data.target_location', null);

    $this->assertDatabaseHas('buyer_demands', [
        'buyer_id' => $fixture['buyer']->id,
        'target_location_id' => null,
    ]);
});

it('validates volume dates and active master data', function () {
    Carbon::setTestNow('2026-09-12');
    $fixture = buyerDemandFixture();
    $fixture['fishSize']->update(['is_active' => false]);
    $fixture['location']->update(['is_active' => false]);

    $this->actingAs($fixture['buyer'])
        ->postJson('/api/v1/buyer-demands', [
            ...validDemandPayload($fixture),
            'required_volume_kg' => 0,
            'need_start_date' => '2026-09-11',
            'need_end_date' => '2026-09-10',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'fish_size_id',
            'required_volume_kg',
            'target_location_id',
            'need_start_date',
            'need_end_date',
        ]);

    $this->assertDatabaseCount('buyer_demands', 0);
});

it('lists only the authenticated buyer demands using status filter and newest order', function () {
    $fixture = buyerDemandFixture();
    $otherBuyer = User::factory()->create();
    $otherBuyer->roles()->attach(Role::query()->where('code', 'buyer')->value('id'), ['created_at' => now()]);

    $base = [
        'target_location_id' => $fixture['location']->id,
        'commodity_id' => $fixture['commodity']->id,
        'fish_size_id' => $fixture['fishSize']->id,
        'required_volume_kg' => 100,
        'need_start_date' => '2026-09-20',
        'need_end_date' => '2026-09-27',
    ];
    BuyerDemand::query()->create([...$base, 'buyer_id' => $fixture['buyer']->id, 'status' => 'active', 'notes' => 'lebih lama', 'created_at' => '2026-09-10']);
    BuyerDemand::query()->create([...$base, 'buyer_id' => $fixture['buyer']->id, 'status' => 'active', 'notes' => 'lebih baru', 'created_at' => '2026-09-11']);
    BuyerDemand::query()->create([...$base, 'buyer_id' => $fixture['buyer']->id, 'status' => 'fulfilled']);
    BuyerDemand::query()->create([...$base, 'buyer_id' => $otherBuyer->id, 'status' => 'active', 'notes' => 'milik orang lain']);

    $this->actingAs($fixture['buyer'])
        ->getJson('/api/v1/buyer-demands?status=active&per_page=1')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.notes', 'lebih baru')
        ->assertJsonMissing(['notes' => 'milik orang lain']);
});
