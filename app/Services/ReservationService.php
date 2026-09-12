<?php

namespace App\Services;

use App\Models\HarvestPlan;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(private readonly SupplyAvailabilityService $availability) {}

    /**
     * @param  array{volume_kg: numeric-string|int|float, notes?: string|null}  $data
     */
    public function create(User $buyer, HarvestPlan $harvestPlan, array $data): Reservation
    {
        return DB::transaction(function () use ($buyer, $harvestPlan, $data): Reservation {
            $plan = HarvestPlan::query()
                ->with('commodity:id,code,is_active')
                ->whereKey($harvestPlan->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($plan->status !== 'planned' || $plan->harvest_date->isBefore(today()) || $plan->commodity?->code !== 'bandeng' || ! $plan->commodity->is_active) {
                throw ValidationException::withMessages([
                    'volume_kg' => 'Pasokan ini tidak lagi dapat direservasi.',
                ]);
            }

            $available = $this->availability->calculate($plan)['available'];
            $requested = (float) $data['volume_kg'];

            if ($requested > $available) {
                throw ValidationException::withMessages([
                    'volume_kg' => 'Jumlah reservasi melebihi volume tersedia saat ini.',
                ]);
            }

            return $buyer->reservations()->create([
                'harvest_plan_id' => $plan->id,
                'buyer_demand_id' => null,
                'reserved_volume_kg' => $requested,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'expires_at' => now()->addHours(config('reservations.pending_expiry_hours')),
            ])->load('harvestPlan:id,pond_name');
        }, attempts: 3);
    }
}
