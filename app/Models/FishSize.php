<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['commodity_id', 'code', 'name', 'min_weight_gram', 'max_weight_gram', 'is_active'])]
class FishSize extends Model
{
    protected function casts(): array
    {
        return [
            'min_weight_gram' => 'decimal:2',
            'max_weight_gram' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    public function buyerDemands(): HasMany
    {
        return $this->hasMany(BuyerDemand::class);
    }
}
