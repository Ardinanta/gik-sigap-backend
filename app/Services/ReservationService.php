<?php

namespace App\Services;

use App\Models\HarvestPlan;
use App\Models\Partnership;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\PartnershipActivityNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(private readonly SupplyAvailabilityService $availability) {}

    /**
     * @param  array{volume_kg: numeric-string|int|float, notes?: string|null}  $data
     */
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Reservation>
     */
    public function paginateForBuyer(User $buyer, array $filters): LengthAwarePaginator
    {
        $this->expirePendingForBuyer($buyer);
        $statusFilter = $filters['status'] ?? null;

        return $buyer->reservations()
            ->with([
                'harvestPlan.farmer:id,name,phone',
                'harvestPlan.location:id,code,name',
                'harvestPlan.fishSize:id,code,name',
            ])
            ->when(
                $statusFilter === 'pending' || $statusFilter === null,
                fn (Builder $query) => $query->where(function (Builder $inner) use ($statusFilter): void {
                    if ($statusFilter === 'pending') {
                        // Only truly live pending rows
                        $inner->where('status', 'pending')
                            ->where(function (Builder $q): void {
                                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                            });
                    } else {
                        // All statuses, but pending must still be live
                        $inner->where('status', '!=', 'pending')
                            ->orWhere(function (Builder $q): void {
                                $q->where('status', 'pending')
                                    ->where(function (Builder $r): void {
                                        $r->whereNull('expires_at')->orWhere('expires_at', '>', now());
                                    });
                            });
                    }
                }),
            )
            ->when(
                $statusFilter !== null && $statusFilter !== 'pending',
                fn (Builder $query, mixed $status) => $query->where('status', $statusFilter),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 12))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Reservation>
     */
    public function paginateForFarmer(User $farmer, array $filters): LengthAwarePaginator
    {
        return Reservation::query()
            ->with(['buyer:id,name,phone', 'harvestPlan.location:id,code,name', 'harvestPlan.fishSize:id,code,name'])
            ->whereHas('harvestPlan', fn (Builder $query) => $query->where('farmer_id', $farmer->id))
            ->where('status', $filters['status'] ?? 'pending')
            ->when(($filters['status'] ?? 'pending') === 'pending', fn (Builder $query) => $query->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            }))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 12))->withQueryString();
    }

    public function confirmForFarmer(User $farmer, Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($farmer, $reservation): Reservation {
            $locked = Reservation::query()->with('harvestPlan')->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->harvestPlan?->farmer_id === (int) $farmer->id, 404);
            $plan = HarvestPlan::query()->whereKey($locked->harvest_plan_id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'confirmed') {
                $this->createPartnership($locked, $farmer, $plan);

                return $locked;
            }

            if ($locked->status !== 'pending' || ($locked->expires_at && $locked->expires_at->isPast())) {
                if ($locked->status === 'pending') {
                    $locked->update(['status' => 'expired']);
                }
                throw ValidationException::withMessages(['reservation' => 'Hanya reservasi pending yang masih berlaku dapat dikonfirmasi.']);
            }

            if ($plan->status !== 'planned' || (float) $plan->asking_price_per_kg <= 0) {
                throw ValidationException::withMessages(['reservation' => 'Rencana panen harus aktif dan harga harus tersedia sebelum kesepakatan dikonfirmasi.']);
            }

            $available = round($this->availability->calculate($plan)['available'], 2);
            $requested = round((float) $locked->reserved_volume_kg, 2);

            if (round($requested - $available, 2) > 0.01) {
                throw ValidationException::withMessages(['reservation' => 'Volume pasokan tersedia tidak lagi mencukupi pengajuan ini.']);
            }

            $locked->update(['status' => 'confirmed', 'confirmed_at' => now(), 'expires_at' => null]);
            $partnership = $this->createPartnership($locked, $farmer, $plan);
            $buyer = $locked->buyer()->first();
            $buyer?->notify(new PartnershipActivityNotification(
                'reservation_confirmed',
                'Pengajuan pasokan diterima',
                "Petambak menerima pengajuan pasokan dari {$plan->pond_name}.",
                "/app/buyer/kemitraan/{$partnership->id}",
                $partnership->id,
            ));

            return $locked->load(['buyer:id,name,phone', 'harvestPlan.location:id,code,name', 'harvestPlan.fishSize:id,code,name']);
        }, attempts: 3);
    }

    public function cancel(User $buyer, Reservation $reservation): Reservation
    {
        abort_unless((int) $reservation->buyer_id === (int) $buyer->id, 404);

        return DB::transaction(function () use ($buyer, $reservation): Reservation {
            $locked = Reservation::query()
                ->whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless((int) $locked->buyer_id === (int) $buyer->id, 404);

            $plan = HarvestPlan::query()->whereKey($locked->harvest_plan_id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending' || ($locked->expires_at && $locked->expires_at->isPast())) {
                if ($locked->status === 'pending') {
                    $locked->update(['status' => 'expired']);
                }

                throw ValidationException::withMessages([
                    'reservation' => 'Hanya reservasi pending yang masih berlaku dapat dibatalkan.',
                ]);
            }

            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $farmer = $plan->farmer()->first();
            $farmer?->notify(new PartnershipActivityNotification(
                'reservation_cancelled',
                'Pengajuan pasokan dibatalkan',
                "{$buyer->name} membatalkan pengajuan pasokan dari {$plan->pond_name}.",
                '/app/farmer/kemitraan',
                $locked->id,
            ));

            return $locked->load([
                'harvestPlan.farmer:id,name,phone',
                'harvestPlan.location:id,code,name',
                'harvestPlan.fishSize:id,code,name',
            ]);
        }, attempts: 3);
    }

    private function createPartnership(Reservation $reservation, User $farmer, HarvestPlan $plan): Partnership
    {
        $existing = $reservation->partnership()->first();
        if ($existing) {
            return $existing;
        }

        if ($plan->status !== 'planned' || (float) $plan->asking_price_per_kg <= 0) {
            throw ValidationException::withMessages(['reservation' => 'Rencana panen harus aktif dan harga harus tersedia sebelum kesepakatan dikonfirmasi.']);
        }

        $partnership = new Partnership;
        $partnership->reservation_id = $reservation->id;
        $partnership->fill([
            'initiated_by_user_id' => $reservation->buyer_id,
            'status' => 'matched',
            'agreed_volume_kg' => $reservation->reserved_volume_kg,
            'agreed_price_per_kg' => $plan->asking_price_per_kg,
            'notes' => $reservation->notes,
            'started_at' => $reservation->confirmed_at ?? now(),
        ])->save();
        $partnership->histories()->create([
            'changed_by_user_id' => $farmer->id, 'from_status' => null, 'to_status' => 'matched',
            'note' => 'Reservasi pembeli diterima oleh petambak.', 'changed_at' => $reservation->confirmed_at ?? now(),
        ]);

        return $partnership;
    }

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

            $reservation = $buyer->reservations()->create([
                'harvest_plan_id' => $plan->id,
                'buyer_demand_id' => null,
                'reserved_volume_kg' => $requested,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'expires_at' => now()->addHours(config('reservations.pending_expiry_hours')),
            ]);

            $farmer = $plan->farmer()->first();
            $farmer?->notify(new PartnershipActivityNotification(
                'reservation_requested',
                'Pengajuan pasokan baru',
                "{$buyer->name} mengajukan {$requested} kg pasokan dari {$plan->pond_name}.",
                '/app/farmer/kemitraan',
                $reservation->id,
            ));

            return $reservation->load('harvestPlan:id,pond_name');
        }, attempts: 3);
    }

    private function expirePendingForBuyer(User $buyer): void
    {
        $buyer->reservations()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }
}
