<?php

namespace App\Http\Resources\Api\V1;

use App\Models\HarvestPlan;
use App\Services\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin HarvestPlan */
class CatalogResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $allocated = (float) ($this->allocated_volume_kg ?? 0);
        $reserved = (float) ($this->reserved_volume_kg ?? 0);
        $available = max((float) $this->estimated_volume_kg - $allocated - $reserved, 0);

        return [
            'id' => $this->id,
            'farmer_name' => $this->farmer?->name,
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'code' => $this->location->code,
                'name' => $this->location->name,
            ]),
            'fish_size' => $this->whenLoaded('fishSize', fn () => [
                'id' => $this->fishSize->id,
                'code' => $this->fishSize->code,
                'name' => $this->fishSize->name,
            ]),
            'harvest_date' => $this->harvest_date?->format('Y-m-d'),
            'pond_name' => $this->pond_name,
            'pond_address' => $this->pond_address,
            'coordinates' => $this->latitude !== null && $this->longitude !== null ? [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ] : null,
            'estimated_volume_kg' => $this->estimated_volume_kg,
            'allocated_volume_kg' => number_format($allocated, 2, '.', ''),
            'reserved_volume_kg' => number_format($reserved, 2, '.', ''),
            'available_volume_kg' => number_format($available, 2, '.', ''),
            'asking_price_per_kg' => $this->asking_price_per_kg,
            'notes' => $this->notes,
            'photo_url' => $this->photo_path
                ? Storage::disk('public')->url($this->photo_path)
                : null,
            'whatsapp_url' => app(CatalogService::class)->whatsappUrl($this->resource),
        ];
    }
}
