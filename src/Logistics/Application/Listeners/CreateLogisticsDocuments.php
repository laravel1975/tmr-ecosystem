<?php

namespace TmrEcosystem\Logistics\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Logistics\Domain\Services\OrderFulfillmentService;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
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
        // 1. [Safety Check] ตรวจสอบว่า Event มีข้อมูล Snapshot มาหรือไม่
        // (เผื่อกรณีช่วงเปลี่ยนผ่านที่ Event เก่ายังค้างใน Queue)
        if (!isset($event->orderSnapshot)) {
            Log::warning("Logistics: Received OrderConfirmed without Snapshot. Falling back to legacy mode (if supported) or skipping.");
            // ในที่นี้ถ้าไม่มี Snapshot เราอาจจะ Fail หรือใช้ Logic เดิม (Legacy) ก็ได้
            return;
        }

        $snapshot = $event->orderSnapshot;
        $orderId = $snapshot->orderId;

        // 2. [Idempotency Check] (Logic จาก Day 2)
        if (PickingSlip::where('order_id', $orderId)->exists()) {
            Log::info("Logistics: [Idempotency] Picking Slip for Order {$orderId} already exists. Skipping.");
            return;
        }

        try {
            Log::info("Logistics: Processing Order {$snapshot->orderNumber} via Event Snapshot.");

            // 3. [Day 5] เรียก Service แบบใหม่ที่รับ DTO
            $this->fulfillmentService->fulfillOrderFromSnapshot($snapshot);

        } catch (Exception $e) {
            Log::error("Logistics: Failed to fulfill Order {$orderId}. Error: " . $e->getMessage());
            throw $e;
        }
    }
}
