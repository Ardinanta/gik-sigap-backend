<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'is_active'])]
class Commodity extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function fishSizes(): HasMany
    {
        return $this->hasMany(FishSize::class);
    }

    public function buyerDemands(): HasMany
    {
        return $this->hasMany(BuyerDemand::class);
    }
}
