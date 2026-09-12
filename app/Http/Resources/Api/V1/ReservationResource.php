<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'harvest_plan' => $this->whenLoaded('harvestPlan', fn () => [
                'id' => $this->harvestPlan->id,
                'pond_name' => $this->harvestPlan->pond_name,
            ]),
            'reserved_volume_kg' => $this->reserved_volume_kg,
            'notes' => $this->notes,
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }
}
