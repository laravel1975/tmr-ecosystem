<?php

namespace TmrEcosystem\Sales\Application\UseCases;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Sales\Application\DTOs\UpdateOrderDto;
use TmrEcosystem\Sales\Domain\Aggregates\Order;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;
use TmrEcosystem\Sales\Domain\Repositories\OrderRepositoryInterface;
use TmrEcosystem\Sales\Domain\Services\ProductCatalogInterface;
use TmrEcosystem\Communication\Infrastructure\Persistence\Models\CommunicationMessage;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Sales\Domain\Events\OrderUpdated;
use TmrEcosystem\Sales\Domain\Services\CreditCheckService;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
// ✅ [เพิ่ม] Import DTOs สำหรับ Snapshot
use TmrEcosystem\Sales\Application\DTOs\OrderSnapshotDto;
use TmrEcosystem\Sales\Application\DTOs\OrderItemSnapshotDto;

class UpdateOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductCatalogInterface $productCatalog,
        private CreditCheckService $creditCheckService
    ) {}

    public function handle(string $orderId, UpdateOrderDto $dto): Order
    {
        Log::info("UpdateOrderUseCase: Starting update for Order ID {$orderId}");

        $order = $this->orderRepository->findById($orderId);
        if (!$order) throw new Exception("Order not found");

        if (in_array($order->getStatus(), [OrderStatus::Cancelled, OrderStatus::Completed])) {
            throw new Exception("Cannot update finalized orders.");
        }

        // [GUARD 1] Logistics Status Check
        $pickingSlip = PickingSlip::where('order_id', $order->getId())->first();
        if ($pickingSlip && in_array($pickingSlip->status, ['in_progress', 'done', 'packed', 'shipped'])) {
            throw new Exception("Cannot update order: Picking process has already started. Please contact warehouse.");
        }

        $oldTotal = $order->getTotalAmount();
        $wasConfirmed = $order->getStatus() === OrderStatus::Confirmed;

        $productIds = array_map(fn($item) => $item->productId, $dto->items);
        $products = $this->productCatalog->getProductsByIds($productIds);

        // [GUARD 2] Financial Control Check
        $newGrandTotal = 0;
        foreach ($dto->items as $itemDto) {
            if ($itemDto->quantity <= 0) continue;
            $product = $products[$itemDto->productId] ?? null;
            if ($product) {
                $newGrandTotal += $product->price * $itemDto->quantity;
            }
        }

        if (($wasConfirmed || $dto->confirmOrder) && $newGrandTotal > $oldTotal) {
            $increaseAmount = $newGrandTotal - $oldTotal;
            $this->creditCheckService->canPlaceOrder($order->getCustomerId(), $increaseAmount);
        }

        return DB::transaction(function () use ($order, $dto, $products, $oldTotal, $orderId, $wasConfirmed) {

            $order->updateDetails($dto->customerId, $dto->note, $dto->paymentTerms);

            $order->clearItems();

            foreach ($dto->items as $itemDto) {
                if ($itemDto->quantity <= 0) continue;

                $product = $products[$itemDto->productId] ?? null;
                if (!$product) continue;

                $order->addItem(
                    productId: $product->id,
                    productName: $product->name,
                    price: $product->price,
                    quantity: $itemDto->quantity,
                    id: $itemDto->id
                );
            }

            $isJustConfirmed = false;
            if ($dto->confirmOrder && $order->getStatus() === OrderStatus::Draft) {
                $order->confirm();
                $isJustConfirmed = true;
            }

            $this->orderRepository->save($order);

            $newTotal = $order->getTotalAmount();
            if ($order->getStatus() === OrderStatus::Confirmed && abs($oldTotal - $newTotal) > 0.01) {
                $diff = $newTotal - $oldTotal;
                $sign = $diff > 0 ? '+' : '';
                $logMessage = sprintf(
                    "มีการแก้ไขรายการสินค้า: ยอดรวมเปลี่ยนจาก %s ฿ เป็น %s ฿ (ส่วนต่าง: %s%s ฿)",
                    number_format($oldTotal, 2),
                    number_format($newTotal, 2),
                    $sign,
                    number_format($diff, 2)
                );

                CommunicationMessage::create([
                    'user_id' => auth()->id(),
                    'body' => $logMessage,
                    'type' => 'notification',
                    'model_type' => 'sales_order',
                    'model_id' => $orderId
                ]);
            }

            if ($isJustConfirmed) {
                // ✅ [DEBUG MODE] ใส่ dd($item) เพื่อดูโครงสร้างข้อมูลจริงๆ
                // เมื่อกดรันแล้ว ให้แคปหน้าจอผลลัพธ์ หรือก๊อปปี้ชื่อตัวแปรที่เห็นมาให้ผมครับ
                // ✅ [FINAL FIX] Map ข้อมูลตามโครงสร้างจริงที่เห็นใน dd()
                $itemsSnapshot = $order->getItems()->map(function ($item) {
                    return new OrderItemSnapshotDto(
                        $item->id,                          // มีอยู่จริง (เห็นในรูปบรรทัดรองสุดท้าย)
                        $item->productId,                   // ใช้ camelCase ตามรูป
                        $item->productName,                 // ใช้ camelCase ตามรูป
                        (float) $item->quantity,
                        (float) $item->unitPrice,
                        (float) $item->unitPrice * (float) $item->quantity // ⚠️ คำนวณ Subtotal เอง เพราะไม่มี property นี้
                    );
                })->toArray();

                $snapshot = new OrderSnapshotDto(
                    orderId: $order->getId(),
                    orderNumber: $order->getOrderNumber(),
                    customerId: $order->getCustomerId(),
                    companyId: $order->getCompanyId(),
                    warehouseId: $order->getWarehouseId(),
                    items: $itemsSnapshot,
                    note: $order->getNote()
                );

                OrderConfirmed::dispatch($order->getId(), $snapshot);
            } elseif ($wasConfirmed) {
                OrderUpdated::dispatch($order);
            }

            return $order;
        });
    }
}
