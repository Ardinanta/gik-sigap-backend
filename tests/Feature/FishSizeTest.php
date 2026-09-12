<?php

use App\Models\Commodity;
use App\Models\FishSize;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns only active bandeng sizes ordered by weight', function () {
    $bandeng = Commodity::query()->create(['code' => 'bandeng', 'name' => 'Bandeng', 'is_active' => true]);
    $other = Commodity::query()->create(['code' => 'nila', 'name' => 'Nila', 'is_active' => true]);

    FishSize::query()->create(['commodity_id' => $bandeng->id, 'code' => 'large', 'name' => 'Besar', 'min_weight_gram' => 500, 'is_active' => true]);
    FishSize::query()->create(['commodity_id' => $bandeng->id, 'code' => 'small', 'name' => 'Kecil', 'min_weight_gram' => 125, 'is_active' => true]);
    FishSize::query()->create(['commodity_id' => $bandeng->id, 'code' => 'inactive', 'name' => 'Tidak Aktif', 'min_weight_gram' => 50, 'is_active' => false]);
    FishSize::query()->create(['commodity_id' => $other->id, 'code' => 'small', 'name' => 'Nila Kecil', 'min_weight_gram' => 100, 'is_active' => true]);

    $this->getJson('/api/v1/fish-sizes')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Kecil')
        ->assertJsonPath('data.1.name', 'Besar')
        ->assertJsonMissing(['name' => 'Tidak Aktif'])
        ->assertJsonMissing(['name' => 'Nila Kecil']);
});
