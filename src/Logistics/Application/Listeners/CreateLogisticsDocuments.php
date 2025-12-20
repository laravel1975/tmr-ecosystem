<?php

namespace TmrEcosystem\Logistics\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // ✅ Use Cache for Atomic Lock
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Logistics\Domain\Services\OrderFulfillmentService;
use Exception;

class CreateLogisticsDocuments implements ShouldQueue
{
    use InteractsWithQueue;

    // Retry settings for resiliency
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

        // ✅ REFACTOR: Idempotency Key Pattern
        // ใช้ Cache Lock เพื่อป้องกัน Race Condition กรณี Event ถูกส่งมาซ้ำ (At-least-once delivery)
        $lockKey = "logistics:processing_order:{$orderId}";

        // Lock for 10 seconds. If locked, it means another worker is processing this order.
        if (!Cache::add($lockKey, true, 10)) {
            Log::info("Logistics: Order {$orderId} is currently being processed by another worker. Skipping.");
            return;
        }

        try {
            Log::info("Logistics: Processing Order {$snapshot->orderNumber} from Event.");

            // Delegate complex logic to Domain Service
            $this->fulfillmentService->fulfillOrderFromSnapshot($snapshot);

        } catch (Exception $e) {
            Log::error("Logistics: Failed to fulfill Order {$orderId}. Error: " . $e->getMessage());

            // Release lock immediately on failure so it can be retried by queue mechanism
            Cache::forget($lockKey);
            throw $e;
        }

        // Note: Lock implies a short TTL processing window.
        // For strict "Process Once Forever", check DB existence inside service.
    }
}
