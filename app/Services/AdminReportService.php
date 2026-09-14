<?php

namespace App\Services;

use App\Models\BuyerDemand;
use App\Models\HarvestPlan;
use App\Models\MatchResult;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminReportService
{
    public function __construct(private readonly AdminRiskService $riskService) {}

    /** @return array<string, mixed> */
    public function dashboard(array $filters): array
    {
        [$start, $end] = $this->period($filters, 30);
        $transactions = $this->transactionQuery(['date_from' => $start->toDateString(), 'date_to' => $end->toDateString()]);
        $transactionSummary = $this->summarizeTransactions(clone $transactions);
        $risks = $this->riskService->index($end->toDateString());
        $riskPriorities = $risks['regions']
            ->whereIn('risk_level', ['warning', 'high'])
            ->sort(function (array $left, array $right): int {
                $leftKey = [
                    $left['risk_level'] === 'high' ? 0 : 1,
                    $left['coordination_status'] === 'uncoordinated' ? 0 : 1,
                    -$left['utilization_percentage'],
                ];
                $rightKey = [
                    $right['risk_level'] === 'high' ? 0 : 1,
                    $right['coordination_status'] === 'uncoordinated' ? 0 : 1,
                    -$right['utilization_percentage'],
                ];

                return $leftKey <=> $rightKey;
            })
            ->take(5)
            ->values();

        return [
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'summary' => [
                'planned_supply_kg' => number_format((float) HarvestPlan::query()->where('status', 'planned')->whereBetween('harvest_date', [$start, $end])->sum('estimated_volume_kg'), 2, '.', ''),
                'active_demand_kg' => number_format((float) BuyerDemand::query()->where('status', 'active')->whereDate('need_end_date', '>=', $start)->whereDate('need_start_date', '<=', $end)->sum('required_volume_kg'), 2, '.', ''),
                'active_match_count' => MatchResult::query()->where('status', 'recommended')->whereHas('harvestPlan', fn (Builder $query) => $query->where('status', 'planned'))->whereHas('buyerDemand', fn (Builder $query) => $query->where('status', 'active'))->count(),
                'warning_risk_count' => $risks['summary']['warning_count'],
                'high_risk_count' => $risks['summary']['high_count'],
                'uncoordinated_risk_count' => $risks['summary']['uncoordinated_count'],
                ...$transactionSummary,
            ],
            'risk_period' => $risks['period'],
            'risk_priorities' => $riskPriorities,
            'price_trend' => $this->priceTrend(['date_from' => $start->toDateString(), 'date_to' => $end->toDateString()]),
            'latest_transactions' => (clone $transactions)->with(['seller:id,name', 'buyer:id,name', 'location:id,code,name', 'fishSize:id,code,name'])->latest('transaction_date')->latest('id')->limit(5)->get()->map(fn (Transaction $transaction) => $this->transactionData($transaction))->values(),
        ];
    }

    public function paginateHarvestPlans(array $filters): LengthAwarePaginator
    {
        return $this->harvestPlanQuery($filters)
            ->with(['farmer:id,name', 'location:id,code,name', 'commodity:id,code,name', 'fishSize:id,code,name'])
            ->orderBy('harvest_date')->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    /** @return array{plan_count:int,estimated_volume_kg:string} */
    public function harvestSummary(array $filters): array
    {
        $query = $this->harvestPlanQuery($filters);

        return ['plan_count' => (clone $query)->count(), 'estimated_volume_kg' => number_format((float) (clone $query)->sum('estimated_volume_kg'), 2, '.', '')];
    }

    public function paginateTransactions(array $filters): LengthAwarePaginator
    {
        return $this->transactionQuery($filters)
            ->with(['seller:id,name', 'buyer:id,name', 'location:id,code,name', 'commodity:id,code,name', 'fishSize:id,code,name'])
            ->latest('transaction_date')->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    /** @return array<string, int|string> */
    public function transactionSummary(array $filters): array
    {
        return $this->summarizeTransactions($this->transactionQuery($filters));
    }

    /** @return Collection<int, array{date:string,weighted_average_price:string,volume_kg:string,transaction_count:int}> */
    public function priceTrend(array $filters): Collection
    {
        return $this->transactionQuery($filters)->toBase()
            ->selectRaw('transaction_date AS date')
            ->selectRaw('SUM(price_per_kg * volume_kg) / NULLIF(SUM(volume_kg), 0) AS weighted_average_price')
            ->selectRaw('SUM(volume_kg) AS volume_kg, COUNT(*) AS transaction_count')
            ->groupBy('transaction_date')->orderBy('transaction_date')->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'weighted_average_price' => number_format((float) $row->weighted_average_price, 2, '.', ''),
                'volume_kg' => number_format((float) $row->volume_kg, 2, '.', ''),
                'transaction_count' => (int) $row->transaction_count,
            ]);
    }

    /** @return array<string, mixed> */
    public function transactionData(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'partnership_id' => $transaction->partnership_id,
            'seller_name' => $transaction->seller?->name,
            'buyer_name' => $transaction->buyer?->name,
            'location' => $transaction->location ? ['id' => $transaction->location->id, 'code' => $transaction->location->code, 'name' => $transaction->location->name] : null,
            'fish_size' => $transaction->fishSize ? ['id' => $transaction->fishSize->id, 'code' => $transaction->fishSize->code, 'name' => $transaction->fishSize->name] : null,
            'volume_kg' => $transaction->volume_kg,
            'price_per_kg' => $transaction->price_per_kg,
            'total_value' => number_format((float) $transaction->volume_kg * (float) $transaction->price_per_kg, 2, '.', ''),
            'transaction_date' => $transaction->transaction_date?->format('Y-m-d'),
        ];
    }

    private function harvestPlanQuery(array $filters): Builder
    {
        return HarvestPlan::query()
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['location_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('location_id', $id))
            ->when($filters['fish_size_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('fish_size_id', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('harvest_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('harvest_date', '<=', $date))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('pond_name', 'like', '%'.$search.'%')->orWhereHas('farmer', fn (Builder $farmer) => $farmer->where('name', 'like', '%'.$search.'%'));
            }));
    }

    private function transactionQuery(array $filters): Builder
    {
        return Transaction::query()
            ->when($filters['location_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('location_id', $id))
            ->when($filters['fish_size_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('fish_size_id', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '<=', $date))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->whereHas('seller', fn (Builder $seller) => $seller->where('name', 'like', '%'.$search.'%'))->orWhereHas('buyer', fn (Builder $buyer) => $buyer->where('name', 'like', '%'.$search.'%'));
            }));
    }

    /** @return array{transaction_count:int,transaction_volume_kg:string,transaction_value:string,weighted_average_price:string} */
    private function summarizeTransactions(Builder $query): array
    {
        $row = $query->toBase()->selectRaw('COUNT(*) AS transaction_count, COALESCE(SUM(volume_kg), 0) AS volume, COALESCE(SUM(price_per_kg * volume_kg), 0) AS total_value')->first();
        $volume = (float) $row->volume;
        $value = (float) $row->total_value;

        return [
            'transaction_count' => (int) $row->transaction_count,
            'transaction_volume_kg' => number_format($volume, 2, '.', ''),
            'transaction_value' => number_format($value, 2, '.', ''),
            'weighted_average_price' => number_format($volume > 0 ? $value / $volume : 0, 2, '.', ''),
        ];
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function period(array $filters, int $fallbackDays): array
    {
        $end = isset($filters['date_to']) ? CarbonImmutable::parse($filters['date_to']) : CarbonImmutable::today();
        $start = isset($filters['date_from']) ? CarbonImmutable::parse($filters['date_from']) : $end->subDays($fallbackDays - 1);

        return [$start, $end];
    }
}
