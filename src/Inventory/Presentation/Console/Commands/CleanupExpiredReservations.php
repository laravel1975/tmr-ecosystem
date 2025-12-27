<?php

namespace TmrEcosystem\Inventory\Presentation\Console\Commands;

use Illuminate\Console\Command;
use TmrEcosystem\Inventory\Application\Services\ReservationExpirationService;

class CleanupExpiredReservations extends Command
{
    protected $signature = 'inventory:cleanup-reservations';
    protected $description = 'Release expired soft reservations and restore stock availability';

    public function handle(ReservationExpirationService $service): void
    {
        $this->info("Starting reservation cleanup...");
        $count = $service->processExpiredReservations();
        $this->info("Processed {$count} expired reservations.");
    }
}
