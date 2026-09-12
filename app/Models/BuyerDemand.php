<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'buyer_id',
    'target_location_id',
    'commodity_id',
    'fish_size_id',
    'required_volume_kg',
    'need_start_date',
    'need_end_date',
    'status',
    'notes',
])]
class BuyerDemand extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'required_volume_kg' => 'decimal:2',
            'need_start_date' => 'date:Y-m-d',
            'need_end_date' => 'date:Y-m-d',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function targetLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'target_location_id');
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    public function fishSize(): BelongsTo
    {
        return $this->belongsTo(FishSize::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MatchResult::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
