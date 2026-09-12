<?php

namespace App\Services;

use App\Models\HarvestPlan;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SupplyAvailabilityService
{
    public function withTotals(Builder $query): Builder
    {
        return $query
            ->withSum([
                'partnerships as allocated_volume_kg' => fn (Builder $query) => $query
                    ->whereIn('partnerships.status', ['matched', 'completed']),
            ], 'agreed_volume_kg')
            ->withSum([
                'reservations as reserved_volume_kg' => fn (Builder $query) => $this->activeReservations($query),
            ], 'reserved_volume_kg');
    }

    public function whereAvailable(Builder $query): Builder
    {
        return $query->whereRaw('(estimated_volume_kg - COALESCE((SELECT SUM(partnerships.agreed_volume_kg) FROM partnerships INNER JOIN matches ON matches.id = partnerships.match_id WHERE matches.harvest_plan_id = harvest_plans.id AND partnerships.status IN (?, ?)), 0) - COALESCE((SELECT SUM(reservations.reserved_volume_kg) FROM reservations WHERE reservations.harvest_plan_id = harvest_plans.id AND (reservations.status = ? OR (reservations.status = ? AND (reservations.expires_at IS NULL OR reservations.expires_at > ?)))), 0)) > 0', [
            'matched',
            'completed',
            'confirmed',
            'pending',
            now(),
        ]);
    }

    /** @return array{allocated: float, reserved: float, available: float} */
    public function calculate(HarvestPlan $plan): array
    {
        $allocated = (float) DB::table('partnerships')
            ->join('matches', 'matches.id', '=', 'partnerships.match_id')
            ->where('matches.harvest_plan_id', $plan->id)
            ->whereIn('partnerships.status', ['matched', 'completed'])
            ->sum('partnerships.agreed_volume_kg');

        $reserved = (float) $this->activeReservations(
            Reservation::query()->where('harvest_plan_id', $plan->id),
        )->sum('reserved_volume_kg');

        return [
            'allocated' => $allocated,
            'reserved' => $reserved,
            'available' => max((float) $plan->estimated_volume_kg - $allocated - $reserved, 0),
        ];
    }

    private function activeReservations(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('status', 'confirmed')
                ->orWhere(function (Builder $query): void {
                    $query->where('status', 'pending')
                        ->where(function (Builder $query): void {
                            $query->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                });
        });
    }
}
