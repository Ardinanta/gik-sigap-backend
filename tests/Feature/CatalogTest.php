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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function catalogFixture(): array
{
    Carbon::setTestNow('2026-09-12 10:00:00');
    $buyerRole = Role::query()->create(['code' => 'buyer', 'name' => 'Pembeli']);
    $farmerRole = Role::query()->create(['code' => 'farmer', 'name' => 'Petambak']);
    $adminRole = Role::query()->create(['code' => 'admin', 'name' => 'Admin']);
    $buyer = User::factory()->create();
    $buyer->roles()->attach($buyerRole->id, ['created_at' => now()]);
    $farmer = User::factory()->create(['name' => 'Budi Tambak', 'phone' => '0812-3456-7890']);
    $farmer->roles()->attach($farmerRole->id, ['created_at' => now()]);
    $admin = User::factory()->create();
    $admin->roles()->attach($adminRole->id, ['created_at' => now()]);
    $commodity = Commodity::query()->create(['code' => 'bandeng', 'name' => 'Bandeng', 'is_active' => true]);
    $fishSize = FishSize::query()->create(['commodity_id' => $commodity->id, 'code' => 'medium', 'name' => 'Sedang', 'min_weight_gram' => 250, 'is_active' => true]);
    $location = Location::query()->create(['code' => 'MANYAR', 'name' => 'Manyar', 'type' => 'district', 'is_active' => true]);

    return compact('buyer', 'farmer', 'admin', 'commodity', 'fishSize', 'location');
}

function createCatalogPlan(array $fixture, array $attributes = []): HarvestPlan
{
    return HarvestPlan::query()->create([
        'farmer_id' => $fixture['farmer']->id,
        'location_id' => $fixture['location']->id,
        'commodity_id' => $fixture['commodity']->id,
        'fish_size_id' => $fixture['fishSize']->id,
        'harvest_date' => '2026-09-20',
        'estimated_volume_kg' => 1000,
        'asking_price_per_kg' => 32000,
        'pond_name' => 'Tambak Uji',
        'pond_address' => 'Alamat tambak yang boleh ditampilkan',
        'status' => 'planned',
        ...$attributes,
    ]);
}

it('requires authentication and buyer role for catalog endpoints', function () {
    $fixture = catalogFixture();
    $plan = createCatalogPlan($fixture);

    $this->getJson('/api/v1/catalog')->assertUnauthorized();
    $this->getJson("/api/v1/catalog/{$plan->id}")->assertUnauthorized();
    $this->actingAs($fixture['farmer'])->getJson('/api/v1/catalog')->assertForbidden();
    $this->actingAs($fixture['admin'])->getJson('/api/v1/catalog')->assertForbidden();
});

it('filters available bandeng plans and paginates deterministically', function () {
    $fixture = catalogFixture();
    $first = createCatalogPlan($fixture, ['pond_name' => 'Pertama', 'harvest_date' => '2026-09-18']);
    $second = createCatalogPlan($fixture, ['pond_name' => 'Kedua', 'harvest_date' => '2026-09-19']);
    createCatalogPlan($fixture, ['pond_name' => 'Selesai', 'status' => 'completed']);
    createCatalogPlan($fixture, ['pond_name' => 'Lampau', 'harvest_date' => '2026-09-11']);
    $deleted = createCatalogPlan($fixture, ['pond_name' => 'Dihapus']);
    $deleted->delete();
    $otherCommodity = Commodity::query()->create(['code' => 'nila', 'name' => 'Nila', 'is_active' => true]);
    createCatalogPlan($fixture, ['pond_name' => 'Bukan Bandeng', 'commodity_id' => $otherCommodity->id]);

    $this->actingAs($fixture['buyer'])
        ->getJson("/api/v1/catalog?location_id={$fixture['location']->id}&fish_size_id={$fixture['fishSize']->id}&start_date=2026-09-18&end_date=2026-09-19&per_page=1")
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonMissing(['id' => $second->id])
        ->assertJsonMissing(['pond_name' => 'Selesai'])
        ->assertJsonMissing(['pond_name' => 'Lampau'])
        ->assertJsonMissing(['pond_name' => 'Dihapus'])
        ->assertJsonMissing(['pond_name' => 'Bukan Bandeng']);
});

it('validates catalog filters against active district and bandeng size', function () {
    $fixture = catalogFixture();
    $inactiveLocation = Location::query()->create(['code' => 'OFF', 'name' => 'Nonaktif', 'type' => 'district', 'is_active' => false]);
    $otherCommodity = Commodity::query()->create(['code' => 'nila', 'name' => 'Nila', 'is_active' => true]);
    $otherSize = FishSize::query()->create(['commodity_id' => $otherCommodity->id, 'code' => 'small', 'name' => 'Kecil', 'is_active' => true]);

    $this->actingAs($fixture['buyer'])
        ->getJson("/api/v1/catalog?location_id={$inactiveLocation->id}&fish_size_id={$otherSize->id}&start_date=2026-09-20&end_date=2026-09-19")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['location_id', 'fish_size_id', 'end_date']);
});

it('subtracts only accepted commitments without counting pending requests', function () {
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

    foreach ([['matched', 200], ['completed', 100], ['discussing', 400], ['cancelled', 500]] as [$status, $volume]) {
        $match = MatchResult::query()->create([
            'harvest_plan_id' => $plan->id,
            'buyer_demand_id' => $demand->id,
            'match_score' => 90,
            'algorithm_version' => 'test-'.$status,
            'status' => 'accepted',
        ]);
        Partnership::query()->create([
            'match_id' => $match->id,
            'initiated_by_user_id' => $fixture['buyer']->id,
            'status' => $status,
            'agreed_volume_kg' => $volume,
        ]);
    }

    foreach ([
        ['confirmed', 100, null],
        ['pending', 50, now()->addHour()],
        ['pending', 70, now()->subHour()],
        ['cancelled', 80, null],
        ['expired', 90, null],
    ] as [$status, $volume, $expiresAt]) {
        Reservation::query()->create([
            'harvest_plan_id' => $plan->id,
            'buyer_id' => $fixture['buyer']->id,
            'reserved_volume_kg' => $volume,
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }

    $this->actingAs($fixture['buyer'])
        ->getJson('/api/v1/catalog')
        ->assertOk()
        ->assertJsonPath('data.0.allocated_volume_kg', '300.00')
        ->assertJsonPath('data.0.reserved_volume_kg', '100.00')
        ->assertJsonPath('data.0.available_volume_kg', '600.00');
});

it('excludes exhausted plans and protects catalog detail', function () {
    $fixture = catalogFixture();
    $available = createCatalogPlan($fixture, ['pond_name' => 'Tersedia']);
    $exhausted = createCatalogPlan($fixture, ['pond_name' => 'Habis', 'estimated_volume_kg' => 100]);
    Reservation::query()->create([
        'harvest_plan_id' => $exhausted->id,
        'buyer_id' => $fixture['buyer']->id,
        'reserved_volume_kg' => 120,
        'status' => 'confirmed',
    ]);

    $this->actingAs($fixture['buyer'])
        ->getJson('/api/v1/catalog')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $available->id);

    $this->actingAs($fixture['buyer'])->getJson("/api/v1/catalog/{$exhausted->id}")->assertNotFound();
});

it('returns seller photos without exposing internal storage metadata', function () {
    Storage::fake('public');
    $fixture = catalogFixture();
    $planWithPhoto = createCatalogPlan($fixture, [
        'pond_name' => 'Tambak Berfoto',
        'photo_path' => 'harvest-plans/catalog-photo.jpg',
        'photo_original_name' => 'foto-asli.jpg',
        'photo_mime_type' => 'image/jpeg',
    ]);
    $planWithoutPhoto = createCatalogPlan($fixture, [
        'pond_name' => 'Tambak Tanpa Foto',
        'harvest_date' => '2026-09-21',
    ]);

    $listResponse = $this->actingAs($fixture['buyer'])
        ->getJson('/api/v1/catalog')
        ->assertOk()
        ->assertJsonPath('data.0.id', $planWithPhoto->id)
        ->assertJsonPath('data.0.photo_url', Storage::disk('public')->url('harvest-plans/catalog-photo.jpg'))
        ->assertJsonPath('data.1.id', $planWithoutPhoto->id)
        ->assertJsonPath('data.1.photo_url', null);

    $detailResponse = $this->actingAs($fixture['buyer'])
        ->getJson("/api/v1/catalog/{$planWithPhoto->id}")
        ->assertOk()
        ->assertJsonPath('data.photo_url', Storage::disk('public')->url('harvest-plans/catalog-photo.jpg'));

    foreach ([$listResponse, $detailResponse] as $response) {
        expect($response->getContent())
            ->not->toContain('photo_path')
            ->not->toContain('photo_original_name')
            ->not->toContain('photo_mime_type')
            ->not->toContain('foto-asli.jpg');
    }
});

it('returns a safe whatsapp link without exposing contact pii', function () {
    $fixture = catalogFixture();
    $plan = createCatalogPlan($fixture);

    $listResponse = $this->actingAs($fixture['buyer'])
        ->getJson('/api/v1/catalog')
        ->assertOk()
        ->assertJsonPath('data.0.id', $plan->id)
        ->assertJsonPath('data.0.whatsapp_url', fn (string $url) => str_starts_with($url, 'https://wa.me/6281234567890?text='));

    $detailResponse = $this->actingAs($fixture['buyer'])
        ->getJson("/api/v1/catalog/{$plan->id}")
        ->assertOk()
        ->assertJsonPath('data.farmer_name', 'Budi Tambak')
        ->assertJsonPath('data.whatsapp_url', fn (string $url) => str_starts_with($url, 'https://wa.me/6281234567890?text='));

    expect($listResponse->json('data.0'))->not->toHaveKeys(['phone', 'email']);
    expect($detailResponse->json('data'))->not->toHaveKeys(['phone', 'email']);
    expect($listResponse->getContent())->not->toContain('0812-3456-7890');
    expect($detailResponse->getContent())->not->toContain('0812-3456-7890');

    $fixture['farmer']->update(['phone' => 'tidak-valid']);
    $this->actingAs($fixture['buyer'])
        ->getJson("/api/v1/catalog/{$plan->id}")
        ->assertOk()
        ->assertJsonPath('data.whatsapp_url', null);
});
