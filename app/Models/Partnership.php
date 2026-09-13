<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['match_id', 'reservation_id', 'initiated_by_user_id', 'status', 'seller_weight_kg', 'buyer_weight_kg', 'seller_confirmed_at', 'buyer_confirmed_at', 'handover_version', 'agreed_volume_kg', 'agreed_price_per_kg', 'notes', 'started_at', 'completed_at', 'cancelled_at'])]
class Partnership extends Model
{
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function supplyPlan(): ?HarvestPlan
    {
        return $this->reservation_id ? $this->reservation?->harvestPlan : $this->matchResult?->harvestPlan;
    }

    public function purchasingBuyer(): ?User
    {
        return $this->reservation_id ? $this->reservation?->buyer : $this->matchResult?->buyerDemand?->buyer;
    }

    public static function reservationRelations(): array
    {
        return [
            'reservation.buyer:id,name,phone',
            'reservation.harvestPlan.farmer:id,name,phone',
            'reservation.harvestPlan.location:id,code,name',
            'reservation.harvestPlan.commodity:id,code,name',
            'reservation.harvestPlan.fishSize:id,code,name',
        ];
    }

    protected function casts(): array
    {
        return [
            'seller_weight_kg' => 'decimal:2',
            'buyer_weight_kg' => 'decimal:2',
            'seller_confirmed_at' => 'datetime',
            'buyer_confirmed_at' => 'datetime',
            'handover_version' => 'integer',
            'agreed_volume_kg' => 'decimal:2',
            'agreed_price_per_kg' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function matchResult(): BelongsTo
    {
        return $this->belongsTo(MatchResult::class, 'match_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(PartnershipStatusHistory::class)
            ->orderBy('changed_at')
            ->orderBy('id');
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }
}
