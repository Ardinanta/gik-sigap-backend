<?php

namespace App\Services;

use App\Models\HarvestPlan;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CatalogService
{
    public function __construct(private readonly SupplyAvailabilityService $availability) {}

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
        $query = HarvestPlan::query()
            ->with(['farmer:id,name,phone', 'location:id,code,name', 'fishSize:id,code,name']);

        return $this->availability->whereAvailable(
            $this->availability->withTotals($query)
                ->where('status', 'planned')
                ->whereDate('harvest_date', '>=', today())
                ->whereHas('commodity', fn (Builder $query) => $query
                    ->where('code', 'bandeng')
                    ->where('is_active', true))
                ->when($filters['location_id'] ?? null, fn (Builder $query, mixed $locationId) => $query->where('location_id', $locationId))
                ->when($filters['fish_size_id'] ?? null, fn (Builder $query, mixed $fishSizeId) => $query->where('fish_size_id', $fishSizeId))
                ->when($filters['start_date'] ?? null, fn (Builder $query, mixed $date) => $query->whereDate('harvest_date', '>=', $date))
                ->when($filters['end_date'] ?? null, fn (Builder $query, mixed $date) => $query->whereDate('harvest_date', '<=', $date)),
        );
    }

    public function whatsappUrl(HarvestPlan $harvestPlan): ?string
    {
        $phone = IndonesianPhoneNumber::normalize((string) $harvestPlan->farmer?->phone);

        if ($phone === null) {
            return null;
        }

        $date = Carbon::parse($harvestPlan->harvest_date)->translatedFormat('d F Y');
        $volume = number_format((float) $harvestPlan->estimated_volume_kg, 0, ',', '.');
        $message = "Halo, saya melihat rencana panen bandeng Anda di SIGAP untuk tanggal {$date} dengan estimasi {$volume} kg. Saya ingin berdiskusi mengenai kebutuhan pembelian.";

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }
}
