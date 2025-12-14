<?php

namespace TmrEcosystem\Logistics\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Logistics\Domain\Services\OrderFulfillmentService;
use Exception;

class CreateLogisticsDocuments implements ShouldQueue
{
    use InteractsWithQueue;

    public $tries = 3;
    public $backoff = 10;

    public function __construct(
        private OrderFulfillmentService $fulfillmentService
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $orderId = is_string($event->orderId) ? $event->orderId : $event->orderId->id ?? null;

        if (!$orderId) {
            return;
        }

        try {
            $this->fulfillmentService->fulfillOrder($orderId);
        } catch (Exception $e) {
            throw $e;
        }
    }
}
