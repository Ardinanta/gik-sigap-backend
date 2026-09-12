<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['harvest_plan_id', 'buyer_id', 'buyer_demand_id', 'reserved_volume_kg', 'status', 'expires_at', 'confirmed_at', 'cancelled_at'])]
class Reservation extends Model
{
    protected function casts(): array
    {
        return [
            'reserved_volume_kg' => 'decimal:2',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function harvestPlan(): BelongsTo
    {
        return $this->belongsTo(HarvestPlan::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function buyerDemand(): BelongsTo
    {
        return $this->belongsTo(BuyerDemand::class);
    }
}
