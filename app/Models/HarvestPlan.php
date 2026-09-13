<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['farmer_id', 'location_id', 'commodity_id', 'fish_size_id', 'harvest_date', 'estimated_volume_kg', 'asking_price_per_kg', 'pond_name', 'pond_address', 'latitude', 'longitude', 'status', 'notes', 'photo_path', 'photo_original_name', 'photo_mime_type'])]
class HarvestPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'harvest_date' => 'date:Y-m-d',
            'estimated_volume_kg' => 'decimal:2',
            'asking_price_per_kg' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
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

    public function partnerships(): HasManyThrough
    {
        return $this->hasManyThrough(
            Partnership::class,
            MatchResult::class,
            'harvest_plan_id',
            'match_id',
            'id',
            'id',
        );
    }
}
