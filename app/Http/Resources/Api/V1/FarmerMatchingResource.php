<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MatchResult;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MatchResult */
class FarmerMatchingResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $demand = $this->buyerDemand;
        $buyer = $demand->buyer;
        $plan = $this->harvestPlan;
        $phone = $buyer?->phone ? IndonesianPhoneNumber::normalize($buyer->phone) : null;

        return [
            'id' => $this->id,
            'match_score' => $this->match_score,
            'matched_at' => $this->matched_at,
            'buyer' => [
                'name' => $buyer?->name,
                'whatsapp_url' => $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode(
                    "Halo {$buyer->name}, saya melihat kebutuhan Bandeng Anda di SIGAP dan ingin membahas kecocokan pasokan dari {$plan->pond_name}."
                ) : null,
            ],
            'demand' => [
                'id' => $demand->id,
                'required_volume_kg' => $demand->required_volume_kg,
                'need_start_date' => $demand->need_start_date?->format('Y-m-d'),
                'need_end_date' => $demand->need_end_date?->format('Y-m-d'),
                'fish_size' => [
                    'id' => $demand->fishSize->id,
                    'code' => $demand->fishSize->code,
                    'name' => $demand->fishSize->name,
                ],
                'target_location' => $demand->targetLocation ? [
                    'id' => $demand->targetLocation->id,
                    'code' => $demand->targetLocation->code,
                    'name' => $demand->targetLocation->name,
                ] : null,
            ],
            'harvest_plan' => [
                'id' => $plan->id,
                'pond_name' => $plan->pond_name,
                'estimated_volume_kg' => $plan->estimated_volume_kg,
                'harvest_date' => $plan->harvest_date?->format('Y-m-d'),
                'fish_size' => [
                    'id' => $plan->fishSize->id,
                    'code' => $plan->fishSize->code,
                    'name' => $plan->fishSize->name,
                ],
            ],
        ];
    }
}
