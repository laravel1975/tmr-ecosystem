<?php

namespace TmrEcosystem\Stock\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Stock\Application\Services\StockReservationService;
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use Exception;

class ReserveStockOnOrderConfirmed implements ShouldQueue
{
    use InteractsWithQueue;

    public $tries = 3;

    public function __construct(
        private StockReservationService $reservationService,
        private ItemLookupServiceInterface $itemLookup
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $order = $event->orderSnapshot; // DTO snapshot from Sales

        Log::info("Stock: Reserving stock for Order {$order->orderNumber}");

        try {
            foreach ($order->items as $item) {
                // ต้อง map ProductId ของ Sales -> InventoryItemUuid ของ Stock
                // สมมติว่ามี Lookup Service หรือใช้ ID เดียวกัน
                $itemUuid = $this->itemLookup->findUuidByPartNumber($item['product_id']);

                if (!$itemUuid) {
                    Log::error("Stock: Item not found for reservation: {$item['product_id']}");
                    continue; // หรือ Throw เพื่อ Retry
                }

                $this->reservationService->reserveForOrder(
                    $itemUuid,
                    $order->warehouseId, // ต้องแน่ใจว่า Order ระบุ Warehouse
                    (float) $item['quantity'],
                    $order->orderId
                );
            }

            // Optional: Emit Event 'StockReserved' กลับไปบอก Sales ว่าจองสำเร็จ

        } catch (Exception $e) {
            Log::error("Stock: Reservation Failed for Order {$order->orderNumber}. Error: " . $e->getMessage());

            // Important: ต้องมี Compesating Transaction (Saga)
            // เพื่อไป Reject Order ใน Sales BC หากจองไม่สำเร็จ
            // dispatch(new OrderReservationFailed($order->orderId));

            $this->fail($e);
        }
    }
}
