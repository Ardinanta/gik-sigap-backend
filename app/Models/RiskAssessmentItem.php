<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['risk_assessment_id', 'harvest_plan_id', 'volume_snapshot_kg'])]
class RiskAssessmentItem extends Model
{
    public $timestamps = false;

    protected function casts(): array { return ['volume_snapshot_kg' => 'decimal:2', 'created_at' => 'datetime']; }
    public function assessment(): BelongsTo { return $this->belongsTo(RiskAssessment::class, 'risk_assessment_id'); }
    public function harvestPlan(): BelongsTo { return $this->belongsTo(HarvestPlan::class); }
}
