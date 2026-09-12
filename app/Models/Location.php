<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'code', 'name', 'type', 'latitude', 'longitude', 'is_active'])]
class Location extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function buyerDemands(): HasMany
    {
        return $this->hasMany(BuyerDemand::class, 'target_location_id');
    }

    public function harvestPlans(): HasMany
    {
        return $this->hasMany(HarvestPlan::class);
    }
}
