<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['match_id', 'initiated_by_user_id', 'status', 'agreed_volume_kg', 'agreed_price_per_kg', 'notes', 'started_at', 'completed_at', 'cancelled_at'])]
class Partnership extends Model
{
    protected function casts(): array
    {
        return [
            'agreed_volume_kg' => 'decimal:2',
            'agreed_price_per_kg' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function matchResult(): BelongsTo
    {
        return $this->belongsTo(MatchResult::class, 'match_id');
    }
}
