<?php

namespace TmrEcosystem\Inventory\Application\Listeners;

use TmrEcosystem\Sales\Domain\Events\OrderCreated;
use TmrEcosystem\Inventory\Application\Services\HardReserveService; // Using Service as Facade
use Illuminate\Contracts\Queue\ShouldQueue;

class InitiateSoftReserve implements ShouldQueue
{
    public function __construct(
        private HardReserveService $reserveService
    ) {}

    public function handle(OrderCreated $event): void
    {
        // สร้าง Soft Reserve (มีอายุ 60 นาที Default)
        $this->reserveService->createSoftReservation(
            $event->orderId,
            $event->snapshot->items,
            $event->snapshot->warehouseId
        );
    }
}
