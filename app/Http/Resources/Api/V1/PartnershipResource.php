<?php

namespace App\Http\Resources\Api\V1;

use App\Services\CatalogService;
use App\Support\IndonesianPhoneNumber;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnershipResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $plan = $this->supplyPlan();
        $buyer = $this->purchasingBuyer();
        $buyerPhone = IndonesianPhoneNumber::normalize((string) $buyer?->phone);
        $total = $this->agreed_volume_kg !== null && $this->agreed_price_per_kg !== null
            ? (string) BigDecimal::of($this->agreed_volume_kg)
                ->multipliedBy($this->agreed_price_per_kg)->toScale(2, RoundingMode::HalfUp)
            : null;
        $agreementReady = $this->agreed_volume_kg !== null
            && $this->agreed_price_per_kg !== null
            && (float) $this->agreed_price_per_kg > 0;
        $harvestReached = $plan?->harvest_date !== null && ! $plan->harvest_date->isAfter(today());
        $canConfirmHandover = $this->status === 'matched' && $agreementReady && $harvestReached;
        $handoverUnavailableReason = match (true) {
            $this->status === 'completed' => 'Penyerahan telah selesai dan transaksi sudah dikunci.',
            $this->status !== 'matched' => 'Penyerahan tersedia setelah kemitraan mencapai kesepakatan.',
            ! $agreementReady => 'Volume dan harga harus disepakati sebelum penyerahan.',
            ! $harvestReached => 'Konfirmasi tersedia mulai tanggal panen.',
            default => null,
        };

        return [
            'id' => $this->id,
            'match_id' => $this->match_id,
            'status' => $this->status,
            'handover' => [
                'version' => $this->handover_version ?? 0,
                'can_confirm' => $canConfirmHandover,
                'unavailable_reason' => $handoverUnavailableReason,
                'seller_weight_kg' => $this->seller_weight_kg,
                'buyer_weight_kg' => $this->buyer_weight_kg,
                'seller_confirmed_at' => $this->seller_confirmed_at,
                'buyer_confirmed_at' => $this->buyer_confirmed_at,
            ],
            'buyer' => $buyer ? [
                'id' => $buyer->id,
                'name' => $buyer->name,
                'whatsapp_url' => $buyerPhone ? 'https://wa.me/'.$buyerPhone.'?text='.rawurlencode(
                    "Halo {$buyer->name}, saya menerima pengajuan kemitraan Anda untuk pasokan {$plan?->pond_name} di SIGAP."
                ) : null,
            ] : null,
            'agreed_volume_kg' => $this->agreed_volume_kg,
            'proposed_volume_kg' => $this->matchResult?->matched_volume_kg ?? $this->reservation?->reserved_volume_kg,
            'agreed_price_per_kg' => $this->agreed_price_per_kg,
            'agreed_total' => $total,
            'notes' => $this->notes,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'supply' => $plan ? [
                'id' => $plan->id,
                'farmer_name' => $plan->farmer?->name,
                'pond_name' => $plan->pond_name,
                'location' => $plan->relationLoaded('location') ? [
                    'id' => $plan->location->id,
                    'code' => $plan->location->code,
                    'name' => $plan->location->name,
                ] : null,
                'commodity' => $plan->relationLoaded('commodity') ? [
                    'id' => $plan->commodity->id,
                    'code' => $plan->commodity->code,
                    'name' => $plan->commodity->name,
                ] : null,
                'fish_size' => $plan->relationLoaded('fishSize') ? [
                    'id' => $plan->fishSize->id,
                    'code' => $plan->fishSize->code,
                    'name' => $plan->fishSize->name,
                ] : null,
                'harvest_date' => $plan->harvest_date?->format('Y-m-d'),
                'asking_price_per_kg' => $plan->asking_price_per_kg,
                'whatsapp_url' => app(CatalogService::class)->whatsappUrl($plan),
            ] : null,
            'history' => PartnershipHistoryResource::collection($this->whenLoaded('histories')),
            'transaction' => $this->whenLoaded('transaction', fn () => $this->transaction
                ? new TransactionResource($this->transaction)
                : null),
        ];
    }
}
