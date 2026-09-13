<?php

namespace App\Services;

use App\Models\MatchResult;
use App\Models\Partnership;
use App\Models\User;
use App\Notifications\PartnershipActivityNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnershipService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Partnership>
     */
    public function paginateForBuyer(User $buyer, array $filters): LengthAwarePaginator
    {
        $view = $filters['view'] ?? 'active';

        return $this->buyerQuery($buyer)
            ->with(Partnership::reservationRelations())
            ->with([
                'matchResult.harvestPlan.farmer:id,name,phone',
                'matchResult.harvestPlan.location:id,code,name',
                'matchResult.harvestPlan.commodity:id,code,name',
                'matchResult.harvestPlan.fishSize:id,code,name',
                'transaction.location:id,code,name',
                'transaction.commodity:id,code,name',
                'transaction.fishSize:id,code,name',
            ])
            ->when(
                $view === 'history',
                fn (Builder $query) => $query->whereIn('status', ['completed', 'cancelled']),
                fn (Builder $query) => $query->whereIn('status', ['interested', 'discussing', 'matched']),
            )
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 12))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Partnership>
     */
    public function paginateForFarmer(User $farmer, array $filters): LengthAwarePaginator
    {
        $view = $filters['view'] ?? 'active';

        return $this->farmerQuery($farmer)
            ->with($this->farmerRelations())
            ->when(
                $view === 'history',
                fn (Builder $query) => $query->whereIn('status', ['completed', 'cancelled']),
                fn (Builder $query) => $query->whereIn('status', ['interested', 'discussing', 'matched']),
            )
            ->orderByDesc('started_at')->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 12))->withQueryString();
    }

    /** @return array{active_count: int, agreed_volume_kg: string, harvest_start_date: ?string, harvest_end_date: ?string, pending_request_count: int} */
    public function summaryForFarmer(User $farmer): array
    {
        $row = Partnership::query()
            ->leftJoin('matches', 'matches.id', '=', 'partnerships.match_id')
            ->leftJoin('reservations', 'reservations.id', '=', 'partnerships.reservation_id')
            ->join('harvest_plans', 'harvest_plans.id', '=', DB::raw('COALESCE(matches.harvest_plan_id, reservations.harvest_plan_id)'))
            ->where('harvest_plans.farmer_id', $farmer->id)
            ->selectRaw("COUNT(*) FILTER (WHERE partnerships.status IN ('discussing','matched')) AS active_count, COALESCE(SUM(partnerships.agreed_volume_kg) FILTER (WHERE partnerships.status IN ('matched','completed')), 0) AS agreed_volume_kg, MIN(harvest_plans.harvest_date) FILTER (WHERE partnerships.status IN ('discussing','matched')) AS harvest_start_date, MAX(harvest_plans.harvest_date) FILTER (WHERE partnerships.status IN ('discussing','matched')) AS harvest_end_date, COUNT(*) FILTER (WHERE partnerships.status = 'interested') AS pending_request_count")
            ->first();

        return [
            'active_count' => (int) ($row->active_count ?? 0),
            'agreed_volume_kg' => number_format((float) ($row->agreed_volume_kg ?? 0), 2, '.', ''),
            'harvest_start_date' => $row->harvest_start_date ?? null,
            'harvest_end_date' => $row->harvest_end_date ?? null,
            'pending_request_count' => (int) ($row->pending_request_count ?? 0),
        ];
    }

    public function findForFarmerOrFail(User $farmer, Partnership $partnership): Partnership
    {
        return $this->farmerQuery($farmer)->whereKey($partnership->getKey())->firstOrFail()
            ->load([...$this->farmerRelations(), 'histories.changedBy:id,name']);
    }

    public function confirmForFarmer(User $farmer, Partnership $partnership): Partnership
    {
        return DB::transaction(function () use ($farmer, $partnership): Partnership {
            $locked = $this->farmerQuery($farmer)
                ->with(['matchResult.harvestPlan', 'matchResult.buyerDemand.buyer'])
                ->whereKey($partnership->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'interested') {
                throw ValidationException::withMessages(['partnership' => 'Hanya pengajuan baru yang dapat dikonfirmasi.']);
            }

            $plan = $locked->matchResult?->harvestPlan;
            $volume = (float) ($locked->matchResult?->matched_volume_kg ?? 0);
            $price = (float) ($plan?->asking_price_per_kg ?? 0);

            if ($volume <= 0 || $price <= 0 || $plan?->status !== 'planned') {
                throw ValidationException::withMessages(['partnership' => 'Volume, harga, atau rencana panen tidak lagi valid untuk dikonfirmasi.']);
            }

            $plan = $plan->newQuery()->whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $available = round(app(SupplyAvailabilityService::class)->calculate($plan)['available'], 2);
            if (round($volume - $available, 2) > 0.01) {
                throw ValidationException::withMessages(['partnership' => 'Volume pasokan tersedia tidak lagi mencukupi pengajuan ini.']);
            }

            $locked->update(['status' => 'matched', 'agreed_volume_kg' => $volume, 'agreed_price_per_kg' => $price]);
            $locked->histories()->create([
                'changed_by_user_id' => $farmer->id, 'from_status' => 'interested', 'to_status' => 'matched',
                'note' => 'Pengajuan kemitraan dikonfirmasi oleh petambak.', 'changed_at' => now(),
            ]);

            $buyer = $locked->matchResult?->buyerDemand?->buyer;
            $buyer?->notify(new PartnershipActivityNotification(
                'partnership_confirmed',
                'Pengajuan kemitraan diterima',
                "Petambak menerima pengajuan untuk {$plan->pond_name}. Volume dan harga kini telah disepakati.",
                "/app/buyer/kemitraan/{$locked->id}",
                $locked->id,
            ));

            return $this->findForFarmerOrFail($farmer, $locked);
        }, attempts: 3);
    }

    /**
     * @return array{active_count: int, agreed_volume_kg: string, harvest_start_date: ?string, harvest_end_date: ?string, pending_reservation_count: int}
     */
    public function summaryForBuyer(User $buyer): array
    {
        // Single query with conditional aggregates to replace four separate queries.
        $row = Partnership::query()
            ->leftJoin('matches', 'matches.id', '=', 'partnerships.match_id')
            ->leftJoin('buyer_demands', 'buyer_demands.id', '=', 'matches.buyer_demand_id')
            ->leftJoin('reservations', 'reservations.id', '=', 'partnerships.reservation_id')
            ->join('harvest_plans', 'harvest_plans.id', '=', DB::raw('COALESCE(matches.harvest_plan_id, reservations.harvest_plan_id)'))
            ->whereRaw('COALESCE(buyer_demands.buyer_id, reservations.buyer_id) = ?', [$buyer->id])
            ->selectRaw("
                COUNT(*) FILTER (WHERE partnerships.status IN ('interested','discussing','matched')) AS active_count,
                COALESCE(SUM(partnerships.agreed_volume_kg) FILTER (WHERE partnerships.status IN ('matched','completed')), 0) AS agreed_volume_kg,
                MIN(harvest_plans.harvest_date) FILTER (WHERE partnerships.status IN ('interested','discussing','matched')) AS harvest_start_date,
                MAX(harvest_plans.harvest_date) FILTER (WHERE partnerships.status IN ('interested','discussing','matched')) AS harvest_end_date
            ")
            ->first();

        $pendingReservations = $buyer->reservations()
            ->where('status', 'pending')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        return [
            'active_count' => (int) ($row->active_count ?? 0),
            'agreed_volume_kg' => number_format((float) ($row->agreed_volume_kg ?? 0), 2, '.', ''),
            'harvest_start_date' => $row->harvest_start_date ?? null,
            'harvest_end_date' => $row->harvest_end_date ?? null,
            'pending_reservation_count' => $pendingReservations,
        ];
    }

    public function create(User $buyer, MatchResult $matchResult): Partnership
    {
        try {
            return DB::transaction(function () use ($buyer, $matchResult): Partnership {
                $match = MatchResult::query()
                    ->with(['buyerDemand', 'harvestPlan.farmer', 'partnership'])
                    ->whereKey($matchResult->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless((int) $match->buyerDemand?->buyer_id === (int) $buyer->id, 404);

                if ($match->partnership) {
                    throw ValidationException::withMessages([
                        'match' => 'Rekomendasi ini sudah ditindaklanjuti menjadi kemitraan.',
                    ]);
                }

                if ($match->status !== 'recommended' || $match->buyerDemand?->status !== 'active') {
                    throw ValidationException::withMessages([
                        'match' => 'Rekomendasi ini tidak lagi dapat diajukan sebagai kemitraan.',
                    ]);
                }

                $partnership = Partnership::query()->create([
                    'match_id' => $match->id,
                    'initiated_by_user_id' => $buyer->id,
                    'status' => 'interested',
                    'started_at' => now(),
                ]);

                $partnership->histories()->create([
                    'changed_by_user_id' => $buyer->id,
                    'from_status' => null,
                    'to_status' => 'interested',
                    'note' => 'Kemitraan diajukan oleh pembeli.',
                    'changed_at' => now(),
                ]);

                $match->update(['status' => 'accepted']);

                $farmer = $match->buyerDemand && $match->harvestPlan
                    ? $match->harvestPlan->farmer
                    : null;
                $farmer?->notify(new PartnershipActivityNotification(
                    'partnership_requested',
                    'Pengajuan kemitraan baru',
                    "{$buyer->name} mengajukan kemitraan untuk {$match->harvestPlan->pond_name}.",
                    "/app/farmer/kemitraan/{$partnership->id}",
                    $partnership->id,
                ));

                return $this->loadDetails($partnership);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'match' => 'Rekomendasi ini sudah ditindaklanjuti menjadi kemitraan.',
            ]);
        }
    }

    public function findForBuyerOrFail(User $buyer, Partnership $partnership): Partnership
    {
        $owned = $this->buyerQuery($buyer)
            ->whereKey($partnership->getKey())
            ->firstOrFail();

        return $this->loadDetails($owned);
    }

    private function buyerQuery(User $buyer): Builder
    {
        return Partnership::query()->where(function (Builder $owned) use ($buyer): void {
            $owned->whereHas('reservation', fn (Builder $query) => $query->where('buyer_id', $buyer->id))
                ->orWhereExists(function ($query) use ($buyer): void {
                    $query->selectRaw('1')->from('matches')->join('buyer_demands', 'buyer_demands.id', '=', 'matches.buyer_demand_id')
                        ->whereColumn('matches.id', 'partnerships.match_id')->where('buyer_demands.buyer_id', $buyer->id);
                });
        });
        /*
        return Partnership::query()
            ->whereExists(function ($query) use ($buyer): void {
                $query->selectRaw('1')
                    ->from('matches')
                    ->join('buyer_demands', 'buyer_demands.id', '=', 'matches.buyer_demand_id')
                    ->whereColumn('matches.id', 'partnerships.match_id')
                    ->where('buyer_demands.buyer_id', $buyer->id);
            }); */
    }

    private function farmerQuery(User $farmer): Builder
    {
        return Partnership::query()->where(function (Builder $query) use ($farmer): void {
            $query->whereHas('matchResult.harvestPlan', fn (Builder $query) => $query->where('farmer_id', $farmer->id))
                ->orWhereHas('reservation.harvestPlan', fn (Builder $query) => $query->where('farmer_id', $farmer->id));
        });
    }

    /** @return array<int, string> */
    private function farmerRelations(): array
    {
        return [
            ...Partnership::reservationRelations(),
            'matchResult.buyerDemand.buyer:id,name,phone',
            'matchResult.harvestPlan.farmer:id,name',
            'matchResult.harvestPlan.location:id,code,name',
            'matchResult.harvestPlan.commodity:id,code,name',
            'matchResult.harvestPlan.fishSize:id,code,name',
            'transaction.location:id,code,name', 'transaction.commodity:id,code,name', 'transaction.fishSize:id,code,name',
        ];
    }

    private function loadDetails(Partnership $partnership): Partnership
    {
        return $partnership->load([
            ...Partnership::reservationRelations(),
            'matchResult.harvestPlan.farmer:id,name,phone',
            'matchResult.harvestPlan.location:id,code,name',
            'matchResult.harvestPlan.commodity:id,code,name',
            'matchResult.harvestPlan.fishSize:id,code,name',
            'histories.changedBy:id,name',
            'transaction.location:id,code,name',
            'transaction.commodity:id,code,name',
            'transaction.fishSize:id,code,name',
        ]);
    }
}
