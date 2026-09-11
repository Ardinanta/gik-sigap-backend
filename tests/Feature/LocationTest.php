<?php

use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns active districts ordered by name', function () {
    $regency = Location::query()->create([
        'code' => 'GRESIK',
        'name' => 'Kabupaten Gresik',
        'type' => 'regency',
        'is_active' => true,
    ]);

    Location::query()->create([
        'parent_id' => $regency->id,
        'code' => 'SIDAYU',
        'name' => 'Sidayu',
        'type' => 'district',
        'is_active' => true,
    ]);
    Location::query()->create([
        'parent_id' => $regency->id,
        'code' => 'MANYAR',
        'name' => 'Manyar',
        'type' => 'district',
        'is_active' => true,
    ]);
    Location::query()->create([
        'parent_id' => $regency->id,
        'code' => 'BUNGAH',
        'name' => 'Bungah',
        'type' => 'district',
        'is_active' => false,
    ]);

    $this->getJson('/api/v1/locations?type=district')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Manyar')
        ->assertJsonPath('data.1.name', 'Sidayu')
        ->assertJsonMissing(['name' => 'Bungah'])
        ->assertJsonMissing(['name' => 'Kabupaten Gresik']);
});

it('rejects unsupported location types', function () {
    $this->getJson('/api/v1/locations?type=province')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');
});
