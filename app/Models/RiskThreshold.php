<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['location_id', 'commodity_id', 'period_type', 'threshold_volume_kg', 'warning_ratio', 'effective_from', 'effective_until', 'source_note', 'is_active'])]
class RiskThreshold extends Model
{
    protected function casts(): array
    {
        return [
            'threshold_volume_kg' => 'decimal:2',
            'warning_ratio' => 'decimal:4',
            'effective_from' => 'date:Y-m-d',
            'effective_until' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }
}
