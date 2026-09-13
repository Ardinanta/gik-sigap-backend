<?php

namespace App\Services;

use App\Models\HarvestPlan;
use App\Models\RiskThreshold;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FarmerRiskService
{
    /** @return array<string, mixed> */
    public function dashboard(User $farmer, ?string $week): array
    {
        $periodStart = ($week ? CarbonImmutable::parse($week) : CarbonImmutable::today())
            ->startOfWeek(CarbonImmutable::MONDAY);
        $periodEnd = $periodStart->endOfWeek(CarbonImmutable::SUNDAY);

        $thresholds = RiskThreshold::query()
            ->with(['location:id,code,name', 'commodity:id,code,name'])
            ->where('is_active', true)
            ->where('period_type', 'weekly')
            ->whereDate('effective_from', '<=', $periodEnd)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $periodStart))
            ->whereHas('commodity', fn ($query) => $query->where('code', 'bandeng')->where('is_active', true))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (RiskThreshold $threshold) => $threshold->location_id.'-'.$threshold->commodity_id);

        $totals = $this->volumes($periodStart, $periodEnd);
        $myTotals = $this->volumes($periodStart, $periodEnd, $farmer);
        $regions = $thresholds->map(function (RiskThreshold $threshold) use ($totals, $myTotals, $periodStart, $periodEnd): array {
            $key = $threshold->location_id.'-'.$threshold->commodity_id;
            $total = (float) ($totals->get($key) ?? 0);
            $mine = (float) ($myTotals->get($key) ?? 0);
            $limit = (float) $threshold->threshold_volume_kg;
            $warningAt = $limit * (float) $threshold->warning_ratio;

            return [
                'location' => ['id' => $threshold->location->id, 'code' => $threshold->location->code, 'name' => $threshold->location->name],
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'total_volume_kg' => number_format($total, 2, '.', ''),
                'threshold_volume_kg' => $threshold->threshold_volume_kg,
                'warning_ratio' => $threshold->warning_ratio,
                'utilization_percentage' => $limit > 0 ? round(($total / $limit) * 100, 1) : 0,
                'risk_level' => $total > $limit ? 'high' : ($total >= $warningAt ? 'warning' : 'safe'),
                'my_volume_kg' => number_format($mine, 2, '.', ''),
                'other_volume_kg' => number_format(max($total - $mine, 0), 2, '.', ''),
                'remaining_volume_kg' => number_format(max($limit - $total, 0), 2, '.', ''),
                'source_note' => $threshold->source_note,
            ];
        })->values();

        $myLocationIds = HarvestPlan::query()
            ->where('farmer_id', $farmer->id)
            ->where('status', 'planned')
            ->whereBetween('harvest_date', [$periodStart, $periodEnd])
            ->pluck('location_id')
            ->unique();
        $myRegions = $regions->whereIn('location.id', $myLocationIds)->values();

        return [
            'period' => ['start' => $periodStart->toDateString(), 'end' => $periodEnd->toDateString()],
            'summary' => [
                'safe_count' => $regions->where('risk_level', 'safe')->count(),
                'warning_count' => $regions->where('risk_level', 'warning')->count(),
                'high_count' => $regions->where('risk_level', 'high')->count(),
                'configured_region_count' => $regions->count(),
            ],
            'my_regions' => $myRegions,
            'regions' => $regions,
            'unconfigured_my_region_count' => $myLocationIds->diff($regions->pluck('location.id'))->count(),
        ];
    }

    /** @return Collection<string, float|int> */
    private function volumes(CarbonImmutable $start, CarbonImmutable $end, ?User $farmer = null): Collection
    {
        return HarvestPlan::query()
            ->join('commodities', 'commodities.id', '=', 'harvest_plans.commodity_id')
            ->where('harvest_plans.status', 'planned')
            ->where('commodities.code', 'bandeng')
            ->where('commodities.is_active', true)
            ->whereBetween('harvest_plans.harvest_date', [$start->toDateString(), $end->toDateString()])
            ->when($farmer, fn ($query, User $owner) => $query->where('harvest_plans.farmer_id', $owner->id))
            ->groupBy('harvest_plans.location_id', 'harvest_plans.commodity_id')
            ->selectRaw('harvest_plans.location_id, harvest_plans.commodity_id, SUM(harvest_plans.estimated_volume_kg) AS volume')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->location_id.'-'.$row->commodity_id => (float) $row->volume]);
    }
}
