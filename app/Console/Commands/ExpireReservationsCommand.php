<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Illuminate\Console\Command;

class ExpireReservationsCommand extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Mark pending reservations that have passed their expiry date as expired.';

    public function handle(): int
    {
        $count = Reservation::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} reservation(s).");

        return self::SUCCESS;
    }
}
