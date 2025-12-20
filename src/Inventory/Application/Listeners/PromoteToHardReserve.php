<?php

namespace TmrEcosystem\Inventory\Application\Listeners;

use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Inventory\Application\Services\HardReserveService;
use Illuminate\Contracts\Queue\ShouldQueue;

class PromoteToHardReserve implements ShouldQueue
{
    public function __construct(
        private HardReserveService $reserveService
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        // เปลี่ยนสถานะจาก Soft -> Hard (Commit Stock)
        $this->reserveService->promoteOrderToHardReserve($event->orderId);
    }
}
