<?php

namespace App\Services;

use App\Models\HarvestPlan;
use App\Models\RiskThreshold;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WeeklyRiskService
{
    /**
     * @return array{
     *     start: CarbonImmutable,
     *     end: CarbonImmutable,
     *     regions: Collection<int, array{threshold: RiskThreshold, plans: Collection<int, HarvestPlan>, total: float, limit: float, level: string}>
     * }
     */
    public function calculate(?string $week): array
    {
        $start = ($week ? CarbonImmutable::parse($week) : CarbonImmutable::today())
            ->startOfWeek(CarbonImmutable::MONDAY);
        $end = $start->endOfWeek(CarbonImmutable::SUNDAY);

        $thresholds = RiskThreshold::query()
            ->with(['location:id,code,name', 'commodity:id,code,name'])
            ->where('is_active', true)
            ->where('period_type', 'weekly')
            ->whereDate('effective_from', '<=', $end)
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $start))
            ->whereHas('commodity', fn (Builder $query) => $query->where('code', 'bandeng')->where('is_active', true))
            ->latest('effective_from')
            ->latest('id')
            ->get()
            ->unique(fn (RiskThreshold $threshold) => $threshold->location_id.'-'.$threshold->commodity_id)
            ->values();

        $plans = HarvestPlan::query()
            ->select(['id', 'farmer_id', 'location_id', 'commodity_id', 'fish_size_id', 'pond_name', 'harvest_date', 'estimated_volume_kg'])
            ->with(['farmer:id,name', 'fishSize:id,code,name'])
            ->where('status', 'planned')
            ->whereBetween('harvest_date', [$start, $end])
            ->whereIn('location_id', $thresholds->pluck('location_id'))
            ->whereIn('commodity_id', $thresholds->pluck('commodity_id'))
            ->orderBy('harvest_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (HarvestPlan $plan) => $plan->location_id.'-'.$plan->commodity_id);

        $regions = $thresholds->map(function (RiskThreshold $threshold) use ($plans): array {
            $key = $threshold->location_id.'-'.$threshold->commodity_id;
            $contributors = $plans->get($key, collect());
            $total = (float) $contributors->sum('estimated_volume_kg');
            $limit = (float) $threshold->threshold_volume_kg;
            $warningAt = $limit * (float) $threshold->warning_ratio;

            return [
                'threshold' => $threshold,
                'plans' => $contributors,
                'total' => $total,
                'limit' => $limit,
                'level' => $total > $limit ? 'high' : ($total >= $warningAt ? 'warning' : 'safe'),
            ];
        });

        return compact('start', 'end', 'regions');
    }
}
