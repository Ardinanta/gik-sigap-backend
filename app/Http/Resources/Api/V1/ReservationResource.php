<?php

namespace App\Http\Resources\Api\V1;

use App\Services\CatalogService;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $plan = $this->harvestPlan;
        $buyer = $this->relationLoaded('buyer') ? $this->buyer : null;
        $buyerPhone = $buyer ? IndonesianPhoneNumber::normalize((string) $buyer->phone) : null;
        $estimatedTotal = $plan?->asking_price_per_kg !== null
            ? number_format((float) $this->reserved_volume_kg * (float) $plan->asking_price_per_kg, 2, '.', '')
            : null;

        return [
            'id' => $this->id,
            'buyer' => $buyer ? [
                'id' => $buyer->id,
                'name' => $buyer->name,
                'whatsapp_url' => $buyerPhone ? 'https://wa.me/'.$buyerPhone.'?text='.rawurlencode(
                    "Halo {$buyer->name}, saya menerima permintaan reservasi Anda untuk {$plan?->pond_name} melalui SIGAP."
                ) : null,
            ] : null,
            'harvest_plan' => $this->whenLoaded('harvestPlan', fn () => [
                'id' => $plan->id,
                'pond_name' => $plan->pond_name,
                'farmer_name' => $plan->farmer?->name,
                'location' => $plan->relationLoaded('location') ? [
                    'id' => $plan->location->id,
                    'code' => $plan->location->code,
                    'name' => $plan->location->name,
                ] : null,
                'fish_size' => $plan->relationLoaded('fishSize') ? [
                    'id' => $plan->fishSize->id,
                    'code' => $plan->fishSize->code,
                    'name' => $plan->fishSize->name,
                ] : null,
                'harvest_date' => $plan->harvest_date?->format('Y-m-d'),
                'asking_price_per_kg' => $plan->asking_price_per_kg,
                'whatsapp_url' => app(CatalogService::class)->whatsappUrl($plan),
            ]),
            'reserved_volume_kg' => $this->reserved_volume_kg,
            'estimated_total' => $estimatedTotal,
            'notes' => $this->notes,
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'confirmed_at' => $this->confirmed_at,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
        ];
    }
}
