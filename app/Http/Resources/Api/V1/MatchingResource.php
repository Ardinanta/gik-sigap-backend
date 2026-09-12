<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MatchResult;
use App\Services\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MatchResult */
class MatchingResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $plan = $this->harvestPlan;
        $allocated = (float) ($plan->allocated_volume_kg ?? 0);
        $reserved = (float) ($plan->reserved_volume_kg ?? 0);
        $available = max((float) $plan->estimated_volume_kg - $allocated - $reserved, 0);

        return [
            'id' => $this->id,
            'match_score' => $this->match_score,
            'score_category' => $this->scoreCategory((float) $this->match_score),
            'matched_volume_kg' => $this->matched_volume_kg,
            'score_breakdown' => $this->score_breakdown,
            'algorithm_version' => $this->algorithm_version,
            'matched_at' => $this->matched_at,
            'supply' => [
                'id' => $plan->id,
                'farmer_name' => $plan->farmer?->name,
                'location' => [
                    'id' => $plan->location->id,
                    'code' => $plan->location->code,
                    'name' => $plan->location->name,
                ],
                'fish_size' => [
                    'id' => $plan->fishSize->id,
                    'code' => $plan->fishSize->code,
                    'name' => $plan->fishSize->name,
                ],
                'harvest_date' => $plan->harvest_date?->format('Y-m-d'),
                'pond_name' => $plan->pond_name,
                'pond_address' => $plan->pond_address,
                'estimated_volume_kg' => $plan->estimated_volume_kg,
                'allocated_volume_kg' => number_format($allocated, 2, '.', ''),
                'reserved_volume_kg' => number_format($reserved, 2, '.', ''),
                'available_volume_kg' => number_format($available, 2, '.', ''),
                'asking_price_per_kg' => $plan->asking_price_per_kg,
                'notes' => $plan->notes,
                'whatsapp_url' => app(CatalogService::class)->whatsappUrl($plan),
            ],
        ];
    }

    private function scoreCategory(float $score): string
    {
        return match (true) {
            $score >= 90 => 'Sangat Cocok',
            $score >= 75 => 'Cocok',
            $score >= 60 => 'Cukup Cocok',
            default => 'Tidak Direkomendasikan',
        };
    }
}
