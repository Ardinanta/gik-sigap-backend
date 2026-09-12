<?php

namespace App\Services;

use App\Models\HarvestPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CatalogService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<HarvestPlan>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->catalogQuery($filters)
            ->orderBy('harvest_date')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 12))
            ->withQueryString();
    }

    public function findAvailableOrFail(HarvestPlan $harvestPlan): HarvestPlan
    {
        return $this->catalogQuery()
            ->whereKey($harvestPlan->getKey())
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function catalogQuery(array $filters = []): Builder
    {
        return HarvestPlan::query()
            ->with(['farmer:id,name,phone', 'location:id,code,name', 'fishSize:id,code,name'])
            ->withSum([
                'partnerships as allocated_volume_kg' => fn (Builder $query) => $query
                    ->whereIn('partnerships.status', ['matched', 'completed']),
            ], 'agreed_volume_kg')
            ->withSum([
                'reservations as reserved_volume_kg' => fn (Builder $query) => $query
                    ->where(function (Builder $query): void {
                        $query->where('status', 'confirmed')
                            ->orWhere(function (Builder $query): void {
                                $query->where('status', 'pending')
                                    ->where(function (Builder $query): void {
                                        $query->whereNull('expires_at')
                                            ->orWhere('expires_at', '>', now());
                                    });
                            });
                    }),
            ], 'reserved_volume_kg')
            ->where('status', 'planned')
            ->whereDate('harvest_date', '>=', today())
            ->whereHas('commodity', fn (Builder $query) => $query
                ->where('code', 'bandeng')
                ->where('is_active', true))
            ->when($filters['location_id'] ?? null, fn (Builder $query, mixed $locationId) => $query->where('location_id', $locationId))
            ->when($filters['fish_size_id'] ?? null, fn (Builder $query, mixed $fishSizeId) => $query->where('fish_size_id', $fishSizeId))
            ->when($filters['start_date'] ?? null, fn (Builder $query, mixed $date) => $query->whereDate('harvest_date', '>=', $date))
            ->when($filters['end_date'] ?? null, fn (Builder $query, mixed $date) => $query->whereDate('harvest_date', '<=', $date))
            ->whereRaw('(estimated_volume_kg - COALESCE((SELECT SUM(partnerships.agreed_volume_kg) FROM partnerships INNER JOIN matches ON matches.id = partnerships.match_id WHERE matches.harvest_plan_id = harvest_plans.id AND partnerships.status IN (?, ?)), 0) - COALESCE((SELECT SUM(reservations.reserved_volume_kg) FROM reservations WHERE reservations.harvest_plan_id = harvest_plans.id AND (reservations.status = ? OR (reservations.status = ? AND (reservations.expires_at IS NULL OR reservations.expires_at > ?)))), 0)) > 0', [
                'matched',
                'completed',
                'confirmed',
                'pending',
                now(),
            ]);
    }

    public function whatsappUrl(HarvestPlan $harvestPlan): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $harvestPlan->farmer?->phone);

        if (! $phone) {
            return null;
        }

        if (Str::startsWith($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        } elseif (Str::startsWith($phone, '8')) {
            $phone = '62'.$phone;
        }

        if (! preg_match('/^62[0-9]{8,13}$/', $phone)) {
            return null;
        }

        $date = Carbon::parse($harvestPlan->harvest_date)->translatedFormat('d F Y');
        $volume = number_format((float) $harvestPlan->estimated_volume_kg, 0, ',', '.');
        $message = "Halo, saya melihat rencana panen bandeng Anda di SIGAP untuk tanggal {$date} dengan estimasi {$volume} kg. Saya ingin berdiskusi mengenai kebutuhan pembelian.";

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }
}
