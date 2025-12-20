<?php

namespace TmrEcosystem\Logistics\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // ✅ Use Cache for Idempotency
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
        if (!isset($event->orderSnapshot)) {
            Log::warning("Logistics: Received OrderConfirmed without Snapshot. Skipping.");
            return;
        }

        $snapshot = $event->orderSnapshot;
        $orderId = $snapshot->orderId;

        // ✅ REFACTOR: Use Cache Atomicity for Idempotency
        // This decouples the listener from the specific Database table 'picking_slips'
        // and is faster/safer for race conditions in queue workers.
        $lockKey = "logistics:processing_order:{$orderId}";

        // Lock for 10 seconds to prevent double processing
        if (!Cache::add($lockKey, true, 10)) {
            Log::info("Logistics: Order {$orderId} is currently being processed. Skipping.");
            return;
        }

        try {
            // Optional: Secondary check using Domain Service (Does this order already have a picking slip?)
            // if ($this->fulfillmentService->hasPickingSlip($orderId)) { ... }

            Log::info("Logistics: Processing Order {$snapshot->orderNumber} via Event Snapshot.");

            $this->fulfillmentService->fulfillOrderFromSnapshot($snapshot);

        } catch (Exception $e) {
            Log::error("Logistics: Failed to fulfill Order {$orderId}. Error: " . $e->getMessage());
            Cache::forget($lockKey); // Release lock on failure so it can retry
            throw $e;
        }
    }
}
