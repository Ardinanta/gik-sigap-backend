<?php

namespace App\Http\Resources\Api\V1;

use App\Models\HarvestPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin HarvestPlan */
class FarmerHarvestPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pond_name' => $this->pond_name,
            'pond_address' => $this->pond_address,
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'code' => $this->location->code,
                'name' => $this->location->name,
            ]),
            'fish_size' => $this->whenLoaded('fishSize', fn () => [
                'id' => $this->fishSize->id,
                'code' => $this->fishSize->code,
                'name' => $this->fishSize->name,
                'min_weight_gram' => $this->fishSize->min_weight_gram,
                'max_weight_gram' => $this->fishSize->max_weight_gram,
            ]),
            'estimated_volume_kg' => $this->estimated_volume_kg,
            'asking_price_per_kg' => $this->asking_price_per_kg,
            'harvest_date' => $this->harvest_date?->format('Y-m-d'),
            'status' => $this->status,
            'notes' => $this->notes,
            'photo_url' => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
