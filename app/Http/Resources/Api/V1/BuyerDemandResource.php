<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuyerDemandResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fish_size' => $this->whenLoaded('fishSize', fn () => [
                'id' => $this->fishSize->id,
                'code' => $this->fishSize->code,
                'name' => $this->fishSize->name,
            ]),
            'target_location' => $this->whenLoaded('targetLocation', fn () => $this->targetLocation ? [
                'id' => $this->targetLocation->id,
                'code' => $this->targetLocation->code,
                'name' => $this->targetLocation->name,
            ] : null),
            'required_volume_kg' => $this->required_volume_kg,
            'need_start_date' => $this->need_start_date?->format('Y-m-d'),
            'need_end_date' => $this->need_end_date?->format('Y-m-d'),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
