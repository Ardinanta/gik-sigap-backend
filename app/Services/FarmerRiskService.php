<?php

namespace App\Services;

use App\Models\HarvestPlan;
use App\Models\RiskThreshold;
use App\Models\User;

class FarmerRiskService
{
    public function __construct(private readonly WeeklyRiskService $weeklyRiskService) {}

    /** @return array<string, mixed> */
    public function dashboard(User $farmer, ?string $week): array
    {
        $calculation = $this->weeklyRiskService->calculate($week);
        $periodStart = $calculation['start'];
        $periodEnd = $calculation['end'];
        $regions = $calculation['regions']->map(function (array $region) use ($farmer, $periodStart, $periodEnd): array {
            /** @var RiskThreshold $threshold */
            $threshold = $region['threshold'];
            $total = $region['total'];
            $limit = $region['limit'];
            $mine = (float) $region['plans']->where('farmer_id', $farmer->id)->sum('estimated_volume_kg');

            return [
                'location' => ['id' => $threshold->location->id, 'code' => $threshold->location->code, 'name' => $threshold->location->name],
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'total_volume_kg' => number_format($total, 2, '.', ''),
                'threshold_volume_kg' => $threshold->threshold_volume_kg,
                'warning_ratio' => $threshold->warning_ratio,
                'utilization_percentage' => $limit > 0 ? round(($total / $limit) * 100, 1) : 0,
                'risk_level' => $region['level'],
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
}
