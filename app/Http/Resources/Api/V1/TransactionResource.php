<?php

namespace App\Http\Resources\Api\V1;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'partnership_id' => $this->partnership_id,
            'seller_name' => $this->whenLoaded('seller', fn () => $this->seller?->name),
            'buyer_name' => $this->whenLoaded('buyer', fn () => $this->buyer?->name),
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'code' => $this->location->code,
                'name' => $this->location->name,
            ]),
            'commodity' => $this->whenLoaded('commodity', fn () => [
                'id' => $this->commodity->id,
                'code' => $this->commodity->code,
                'name' => $this->commodity->name,
            ]),
            'fish_size' => $this->whenLoaded('fishSize', fn () => [
                'id' => $this->fishSize->id,
                'code' => $this->fishSize->code,
                'name' => $this->fishSize->name,
            ]),
            'volume_kg' => $this->volume_kg,
            'price_per_kg' => $this->price_per_kg,
            'total_value' => (string) BigDecimal::of($this->volume_kg)
                ->multipliedBy($this->price_per_kg)->toScale(2, RoundingMode::HalfUp),
            'transaction_date' => $this->transaction_date?->format('Y-m-d'),
            'locked_at' => $this->locked_at,
            'created_at' => $this->created_at,
        ];
    }
}
