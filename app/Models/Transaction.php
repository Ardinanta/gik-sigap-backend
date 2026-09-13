<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['partnership_id', 'seller_id', 'buyer_id', 'location_id', 'commodity_id', 'fish_size_id', 'volume_kg', 'price_per_kg', 'transaction_date', 'recorded_by', 'locked_at'])]
class Transaction extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'volume_kg' => 'decimal:2',
            'price_per_kg' => 'decimal:2',
            'transaction_date' => 'date:Y-m-d',
            'locked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
