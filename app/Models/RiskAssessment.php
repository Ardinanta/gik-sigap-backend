<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['location_id', 'commodity_id', 'period_start', 'period_end', 'total_planned_volume_kg', 'threshold_volume_snapshot', 'warning_ratio_snapshot', 'risk_level', 'coordination_status', 'coordinated_by', 'coordinated_at', 'algorithm_version', 'calculated_at'])]
class RiskAssessment extends Model
{
    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'total_planned_volume_kg' => 'decimal:2',
            'threshold_volume_snapshot' => 'decimal:2',
            'warning_ratio_snapshot' => 'decimal:4',
            'coordinated_at' => 'datetime',
            'calculated_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function commodity(): BelongsTo { return $this->belongsTo(Commodity::class); }
    public function coordinator(): BelongsTo { return $this->belongsTo(User::class, 'coordinated_by'); }
    public function items(): HasMany { return $this->hasMany(RiskAssessmentItem::class); }
    public function suggestions(): HasMany { return $this->hasMany(ScheduleSuggestion::class); }
}
