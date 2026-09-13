<?php

namespace App\Policies;

use App\Models\Partnership;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PartnershipPolicy
{
    public function handover(User $user, Partnership $partnership): Response
    {
        $partnership->loadMissing([
            'matchResult.buyerDemand',
            'matchResult.harvestPlan',
            'reservation.buyer',
            'reservation.harvestPlan',
        ]);
        $buyer = $partnership->purchasingBuyer()?->id;
        $farmer = $partnership->supplyPlan()?->farmer_id;

        $allowed = $buyer !== $farmer && (
            ($user->hasRole('buyer') && (int) $buyer === (int) $user->id)
            || ($user->hasRole('farmer') && (int) $farmer === (int) $user->id)
        );

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }
}
