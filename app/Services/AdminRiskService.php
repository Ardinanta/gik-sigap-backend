<?php

namespace App\Services;

use App\Models\HarvestPlan;
use App\Models\RiskAssessment;
use App\Models\RiskThreshold;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminRiskService
{
    public function __construct(private readonly WeeklyRiskService $weeklyRiskService) {}

    /** @return array<string, mixed> */
    public function index(?string $week): array
    {
        $calculation = $this->weeklyRiskService->calculate($week);
        $start = $calculation['start'];
        $end = $calculation['end'];
        $regions = $calculation['regions']->map(function (array $region) use ($start, $end): array {
            /** @var RiskThreshold $threshold */
            $threshold = $region['threshold'];
            /** @var Collection<int, HarvestPlan> $plans */
            $plans = $region['plans'];
            $total = $region['total'];
            $limit = $region['limit'];
            $level = $region['level'];
            $assessment = DB::transaction(function () use ($threshold, $start, $end, $total, $level, $plans): RiskAssessment {
                $assessment = RiskAssessment::query()->where([
                    'location_id' => $threshold->location_id,
                    'commodity_id' => $threshold->commodity_id,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'total_planned_volume_kg' => number_format($total, 2, '.', ''),
                    'threshold_volume_snapshot' => $threshold->threshold_volume_kg,
                    'warning_ratio_snapshot' => $threshold->warning_ratio,
                    'risk_level' => $level,
                    'algorithm_version' => 'v1',
                ])->latest('id')->first();

                if (! $assessment) {
                    $assessment = RiskAssessment::query()->create([
                        'location_id' => $threshold->location_id,
                        'commodity_id' => $threshold->commodity_id,
                        'period_start' => $start,
                        'period_end' => $end,
                        'total_planned_volume_kg' => $total,
                        'threshold_volume_snapshot' => $threshold->threshold_volume_kg,
                        'warning_ratio_snapshot' => $threshold->warning_ratio,
                        'risk_level' => $level,
                        'coordination_status' => 'uncoordinated',
                        'algorithm_version' => 'v1',
                        'calculated_at' => now(),
                    ]);
                    $assessment->items()->createMany($plans->map(fn (HarvestPlan $plan) => [
                        'harvest_plan_id' => $plan->id,
                        'volume_snapshot_kg' => $plan->estimated_volume_kg,
                    ])->all());
                }

                return $assessment;
            });

            return [
                'id' => $assessment->id,
                'location' => ['id' => $threshold->location->id, 'code' => $threshold->location->code, 'name' => $threshold->location->name],
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'total_volume_kg' => number_format($total, 2, '.', ''),
                'threshold_volume_kg' => $threshold->threshold_volume_kg,
                'utilization_percentage' => $limit > 0 ? round(($total / $limit) * 100, 1) : 0,
                'risk_level' => $level,
                'coordination_status' => $assessment->coordination_status,
                'coordinated_at' => $assessment->coordinated_at?->toISOString(),
                'contributor_count' => $plans->count(),
            ];
        })->values();

        return [
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'summary' => [
                'safe_count' => $regions->where('risk_level', 'safe')->count(),
                'warning_count' => $regions->where('risk_level', 'warning')->count(),
                'high_count' => $regions->where('risk_level', 'high')->count(),
                'uncoordinated_count' => $regions->where('coordination_status', 'uncoordinated')->whereIn('risk_level', ['warning', 'high'])->count(),
            ],
            'regions' => $regions,
        ];
    }

    /** @return array<string, mixed> */
    public function show(RiskAssessment $assessment): array
    {
        $assessment->load(['location:id,code,name', 'coordinator:id,name', 'items.harvestPlan' => fn ($query) => $query->withTrashed()->select(['id', 'farmer_id', 'fish_size_id', 'pond_name', 'harvest_date'])->with(['farmer:id,name', 'fishSize:id,code,name']), 'suggestions']);

        return [
            'id' => $assessment->id,
            'location' => ['id' => $assessment->location->id, 'code' => $assessment->location->code, 'name' => $assessment->location->name],
            'period_start' => $assessment->period_start->format('Y-m-d'),
            'period_end' => $assessment->period_end->format('Y-m-d'),
            'total_volume_kg' => $assessment->total_planned_volume_kg,
            'threshold_volume_kg' => $assessment->threshold_volume_snapshot,
            'risk_level' => $assessment->risk_level,
            'coordination_status' => $assessment->coordination_status,
            'coordinator_name' => $assessment->coordinator?->name,
            'coordinated_at' => $assessment->coordinated_at?->toISOString(),
            'contributors' => $assessment->items->map(fn ($item) => [
                'harvest_plan_id' => $item->harvest_plan_id,
                'farmer_name' => $item->harvestPlan?->farmer?->name,
                'pond_name' => $item->harvestPlan?->pond_name,
                'harvest_date' => $item->harvestPlan?->harvest_date?->format('Y-m-d'),
                'fish_size_name' => $item->harvestPlan?->fishSize?->name,
                'volume_kg' => $item->volume_snapshot_kg,
            ])->values(),
            'suggestions' => $assessment->suggestions->map(fn ($suggestion) => [
                'id' => $suggestion->id,
                'harvest_plan_id' => $suggestion->harvest_plan_id,
                'suggested_start_date' => $suggestion->suggested_start_date->format('Y-m-d'),
                'suggested_end_date' => $suggestion->suggested_end_date->format('Y-m-d'),
                'reason' => $suggestion->reason,
                'status' => $suggestion->status,
            ])->values(),
        ];
    }

    public function coordinate(User $admin, RiskAssessment $assessment): RiskAssessment
    {
        return DB::transaction(function () use ($admin, $assessment): RiskAssessment {
            $locked = RiskAssessment::query()->whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->risk_level, ['warning', 'high'], true)) {
                throw ValidationException::withMessages(['assessment' => 'Hanya wilayah berstatus waspada atau risiko tinggi yang dapat dikoordinasikan.']);
            }
            if ($locked->coordination_status === 'uncoordinated') {
                $locked->update(['coordination_status' => 'coordinated', 'coordinated_by' => $admin->id, 'coordinated_at' => now()]);
            }

            return $locked;
        }, attempts: 3);
    }
}
