<?php

use App\Models\Commodity;
use App\Models\FishSize;
use App\Models\HarvestPlan;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function farmerPlanFixture(): array
{
    $farmerRole = Role::query()->firstOrCreate(['code' => 'farmer'], ['name' => 'Petambak']);
    Role::query()->firstOrCreate(['code' => 'buyer'], ['name' => 'Pembeli']);
    $farmer = User::factory()->create();
    $farmer->roles()->attach($farmerRole);
    $commodity = Commodity::query()->create(['code' => 'bandeng', 'name' => 'Bandeng', 'is_active' => true]);
    $size = FishSize::query()->create(['commodity_id' => $commodity->id, 'code' => 'medium', 'name' => 'Sedang', 'min_weight_gram' => 250, 'max_weight_gram' => 400, 'is_active' => true]);
    $location = Location::query()->create(['code' => 'MANYAR', 'name' => 'Manyar', 'type' => 'district', 'is_active' => true]);

    return compact('farmer', 'farmerRole', 'commodity', 'size', 'location');
}

function farmerPlanPayload(array $fixture, array $overrides = []): array
{
    return [
        'pond_name' => 'Tambak Barokah',
        'location_id' => $fixture['location']->id,
        'fish_size_id' => $fixture['size']->id,
        'estimated_volume_kg' => 5000,
        'harvest_date' => today()->addDays(7)->format('Y-m-d'),
        'asking_price_per_kg' => 32000,
        ...$overrides,
    ];
}

it('allows a farmer to create and list only their harvest plans', function () {
    $fixture = farmerPlanFixture();

    $this->actingAs($fixture['farmer'])->postJson('/api/v1/farmer/harvest-plans', farmerPlanPayload($fixture, [
        'farmer_id' => 999,
        'status' => 'completed',
        'commodity_id' => 999,
    ]))->assertCreated()
        ->assertJsonPath('data.pond_name', 'Tambak Barokah')
        ->assertJsonPath('data.status', 'planned');

    $this->assertDatabaseHas('harvest_plans', [
        'farmer_id' => $fixture['farmer']->id,
        'commodity_id' => $fixture['commodity']->id,
        'status' => 'planned',
    ]);

    $this->actingAs($fixture['farmer'])->getJson('/api/v1/farmer/harvest-plans?status=planned')
        ->assertOk()->assertJsonCount(1, 'data');
});

it('hides another farmers plan and rejects non-farmer access', function () {
    $fixture = farmerPlanFixture();
    $other = User::factory()->create();
    $other->roles()->attach($fixture['farmerRole']);
    $plan = HarvestPlan::query()->create([
        ...farmerPlanPayload($fixture),
        'farmer_id' => $fixture['farmer']->id,
        'commodity_id' => $fixture['commodity']->id,
        'status' => 'planned',
    ]);

    $this->actingAs($other)->getJson("/api/v1/farmer/harvest-plans/{$plan->id}")->assertNotFound();
    $this->actingAs($other)->patchJson("/api/v1/farmer/harvest-plans/{$plan->id}", farmerPlanPayload($fixture))->assertNotFound();

    $buyer = User::factory()->create();
    $buyer->roles()->attach(Role::query()->where('code', 'buyer')->value('id'));
    $this->actingAs($buyer)->getJson('/api/v1/farmer/harvest-plans')->assertForbidden();
});

it('validates harvest data and safely stores a photo', function () {
    Storage::fake('public');
    $fixture = farmerPlanFixture();

    $this->actingAs($fixture['farmer'])->post('/api/v1/farmer/harvest-plans', farmerPlanPayload($fixture, [
        'photo' => UploadedFile::fake()->image('tambak.jpg'),
    ]))->assertCreated()->assertJsonPath('data.photo_url', fn ($url) => is_string($url));

    $path = HarvestPlan::query()->firstOrFail()->photo_path;
    Storage::disk('public')->assertExists($path);

    $this->actingAs($fixture['farmer'])->postJson('/api/v1/farmer/harvest-plans', farmerPlanPayload($fixture, [
        'estimated_volume_kg' => 0,
        'harvest_date' => today()->subDay()->format('Y-m-d'),
        'asking_price_per_kg' => -1,
    ]))->assertUnprocessable()->assertJsonValidationErrors(['estimated_volume_kg', 'harvest_date', 'asking_price_per_kg']);
});

it('updates an unbound planned harvest plan', function () {
    $fixture = farmerPlanFixture();
    $plan = HarvestPlan::query()->create([
        ...farmerPlanPayload($fixture),
        'farmer_id' => $fixture['farmer']->id,
        'commodity_id' => $fixture['commodity']->id,
        'status' => 'planned',
    ]);

    $this->actingAs($fixture['farmer'])->patchJson("/api/v1/farmer/harvest-plans/{$plan->id}", farmerPlanPayload($fixture, ['pond_name' => 'Tambak Diperbarui']))
        ->assertOk()->assertJsonPath('data.pond_name', 'Tambak Diperbarui');
});
