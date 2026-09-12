<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['harvest_plan_id', 'buyer_demand_id', 'match_score', 'matched_volume_kg', 'score_breakdown', 'algorithm_version', 'status', 'matched_at'])]
class MatchResult extends Model
{
    protected $table = 'matches';

    protected function casts(): array
    {
        return [
            'match_score' => 'decimal:2',
            'matched_volume_kg' => 'decimal:2',
            'score_breakdown' => 'array',
            'matched_at' => 'datetime',
        ];
    }

    public function harvestPlan(): BelongsTo
    {
        return $this->belongsTo(HarvestPlan::class);
    }

    public function buyerDemand(): BelongsTo
    {
        return $this->belongsTo(BuyerDemand::class);
    }

    public function partnership(): HasOne
    {
        return $this->hasOne(Partnership::class, 'match_id');
    }
}
