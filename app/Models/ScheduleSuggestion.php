<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['risk_assessment_id', 'harvest_plan_id', 'original_date', 'suggested_start_date', 'suggested_end_date', 'reason', 'status', 'responded_at'])]
class ScheduleSuggestion extends Model
{
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected function casts(): array
    {
        return ['original_date' => 'date:Y-m-d', 'suggested_start_date' => 'date:Y-m-d', 'suggested_end_date' => 'date:Y-m-d', 'responded_at' => 'datetime'];
    }

    public function assessment(): BelongsTo { return $this->belongsTo(RiskAssessment::class, 'risk_assessment_id'); }
    public function harvestPlan(): BelongsTo { return $this->belongsTo(HarvestPlan::class); }
}
