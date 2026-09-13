<?php

namespace App\Services;

use App\Models\BuyerDemand;
use App\Models\HarvestPlan;
use App\Models\MatchResult;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MatchingService
{
    public function __construct(private readonly SupplyAvailabilityService $availability) {}

    public function demandForBuyer(User $buyer, BuyerDemand $demand): BuyerDemand
    {
        abort_unless((int) $demand->buyer_id === (int) $buyer->id, 404);

        return $demand->loadMissing(['fishSize', 'targetLocation']);
    }

    /** @return LengthAwarePaginator<MatchResult> */
    public function paginate(User $buyer, BuyerDemand $demand, int $perPage = 12): LengthAwarePaginator
    {
        $demand = $this->demandForBuyer($buyer, $demand);

        return $this->resultsQuery($demand)
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return LengthAwarePaginator<MatchResult> */
    public function paginateForFarmer(User $farmer, ?int $harvestPlanId, int $perPage = 12): LengthAwarePaginator
    {
        return MatchResult::query()
            ->whereHas('harvestPlan', fn (Builder $query) => $query
                ->where('farmer_id', $farmer->id)
                ->where('status', 'planned')
                ->when($harvestPlanId, fn (Builder $query, int $id) => $query->whereKey($id)))
            ->whereHas('buyerDemand', fn (Builder $query) => $query->where('status', 'active'))
            ->where('matches.algorithm_version', config('matching.algorithm_version', 'v1'))
            ->where('matches.status', 'recommended')
            ->with([
                'harvestPlan:id,farmer_id,fish_size_id,pond_name,estimated_volume_kg,harvest_date',
                'harvestPlan.fishSize:id,code,name',
                'buyerDemand:id,buyer_id,target_location_id,fish_size_id,required_volume_kg,need_start_date,need_end_date',
                'buyerDemand.buyer:id,name,phone',
                'buyerDemand.fishSize:id,code,name',
                'buyerDemand.targetLocation:id,code,name',
            ])
            ->orderByDesc('match_score')
            ->orderByDesc('matched_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return Collection<int, MatchResult> */
    public function generate(User $buyer, BuyerDemand $demand): Collection
    {
        $demand = $this->demandForBuyer($buyer, $demand);

        if ($demand->status !== 'active') {
            throw ValidationException::withMessages([
                'demand' => 'Hanya kebutuhan aktif yang dapat dicocokkan.',
            ]);
        }

        $version = (string) config('matching.algorithm_version', 'v1');
        $minimumScore = (float) config('matching.minimum_score', 60);
        $now = now();
        $validIds = [];
        $upsertRows = [];

        foreach ($this->candidates($demand)->get() as $plan) {
            $available = max(
                (float) $plan->estimated_volume_kg
                - (float) ($plan->allocated_volume_kg ?? 0)
                - (float) ($plan->reserved_volume_kg ?? 0),
                0,
            );
            $breakdown = $this->score($demand, $plan, $available);
            $score = round(array_sum($breakdown), 2);

            if ($score < $minimumScore) {
                continue;
            }

            $upsertRows[] = [
                'harvest_plan_id' => $plan->id,
                'buyer_demand_id' => $demand->id,
                'algorithm_version' => $version,
                'match_score' => $score,
                'matched_volume_kg' => $this->matchedVolume($available, (float) $demand->required_volume_kg),
                'score_breakdown' => json_encode($breakdown),
                'status' => 'recommended',
                'matched_at' => $now,
            ];
        }

        if ($upsertRows !== []) {
            MatchResult::upsert(
                $upsertRows,
                ['harvest_plan_id', 'buyer_demand_id', 'algorithm_version'],
                ['match_score', 'matched_volume_kg', 'score_breakdown', 'status', 'matched_at'],
            );

            $validIds = MatchResult::query()
                ->where('buyer_demand_id', $demand->id)
                ->where('algorithm_version', $version)
                ->where('status', 'recommended')
                ->whereIn('harvest_plan_id', array_column($upsertRows, 'harvest_plan_id'))
                ->pluck('id')
                ->all();
        }

        $demand->matches()
            ->where('algorithm_version', $version)
            ->where('status', 'recommended')
            ->when($validIds !== [], fn (Builder $query) => $query->whereNotIn('id', $validIds))
            ->when($validIds === [], fn (Builder $query) => $query)
            ->update(['status' => 'expired']);

        return $this->resultsQuery($demand)->get();
    }

    private function matchedVolume(float $available, float $required): float
    {
        $available = round($available, 2);
        $required = round($required, 2);

        if ($required > $available && round($required - $available, 2) <= 0.01) {
            return $required;
        }

        return min($available, $required);
    }

    public function findForBuyer(User $buyer, MatchResult $match): MatchResult
    {
        abort_unless((int) $match->buyerDemand?->buyer_id === (int) $buyer->id, 404);

        return $this->resultsQuery($match->buyerDemand)
            ->whereKey($match->id)
            ->firstOrFail();
    }

    private function candidates(BuyerDemand $demand): Builder
    {
        $query = HarvestPlan::query()
            ->with(['farmer:id,name,phone', 'location:id,code,name', 'fishSize:id,code,name'])
            ->where('status', 'planned')
            ->whereDate('harvest_date', '>=', today())
            ->whereBetween('harvest_date', [
                $demand->need_start_date->format('Y-m-d'),
                $demand->need_end_date->format('Y-m-d'),
            ])
            ->where('commodity_id', $demand->commodity_id)
            ->where('fish_size_id', $demand->fish_size_id)
            ->whereHas('commodity', fn (Builder $query) => $query
                ->where('code', 'bandeng')
                ->where('is_active', true));

        return $this->availability->whereAvailable($this->availability->withTotals($query));
    }

    /** @return array{size: float, location: float, period: float, volume: float} */
    private function score(BuyerDemand $demand, HarvestPlan $plan, float $available): array
    {
        $weights = config('matching.weights');
        $required = (float) $demand->required_volume_kg;

        return [
            'size' => $demand->fish_size_id === $plan->fish_size_id ? (float) $weights['size'] : 0,
            'location' => $demand->target_location_id === null || $demand->target_location_id === $plan->location_id
                ? (float) $weights['location']
                : 0,
            'period' => $plan->harvest_date->betweenIncluded($demand->need_start_date, $demand->need_end_date)
                ? (float) $weights['period']
                : 0,
            'volume' => $required > 0
                ? round(min($available / $required, 1) * (float) $weights['volume'], 2)
                : 0,
        ];
    }

    private function resultsQuery(BuyerDemand $demand): Builder
    {
        $query = MatchResult::query()
            ->join('harvest_plans', 'harvest_plans.id', '=', 'matches.harvest_plan_id')
            ->where('matches.buyer_demand_id', $demand->id)
            ->where('matches.algorithm_version', config('matching.algorithm_version', 'v1'))
            ->where('matches.status', 'recommended')
            ->select('matches.*')
            ->with([
                'harvestPlan' => fn (BelongsTo $query) => $this->availability->withTotals($query->getQuery())
                    ->with(['farmer:id,name,phone', 'location:id,code,name', 'fishSize:id,code,name']),
            ]);

        return $query
            ->orderByDesc('match_score')
            ->orderBy('harvest_plans.harvest_date')
            ->orderBy('id');
    }
}
