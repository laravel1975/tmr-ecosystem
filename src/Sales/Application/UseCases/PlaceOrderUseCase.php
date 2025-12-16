<?php

namespace TmrEcosystem\Sales\Application\UseCases;

use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use TmrEcosystem\Sales\Application\DTOs\CreateOrderDto;
use TmrEcosystem\Sales\Domain\Aggregates\Order;
use TmrEcosystem\Sales\Domain\Repositories\OrderRepositoryInterface;
use TmrEcosystem\Sales\Domain\Services\ProductCatalogInterface;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;
// ✅ Import เพิ่มเติม
use TmrEcosystem\Communication\Infrastructure\Persistence\Models\CommunicationMessage;
use TmrEcosystem\Customers\Infrastructure\Persistence\Models\Customer;
use TmrEcosystem\Sales\Domain\Services\CreditCheckService;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Sales\Application\DTOs\OrderSnapshotDto;
use TmrEcosystem\Sales\Application\DTOs\OrderItemSnapshotDto;

class PlaceOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductCatalogInterface $productCatalog,
        private CreditCheckService $creditCheckService // ✅ Inject Credit Check Service
    ) {}

    /**
     * @throws Exception
     */
    public function handle(CreateOrderDto $dto): Order
    {
        // 1. Prepare Data (Fetch Products & Prices)
        $productIds = array_map(fn($item) => $item->productId, $dto->items);
        $products = $this->productCatalog->getProductsByIds($productIds);

        // 2. ✅ Resolve Salesperson Logic
        // ถ้า DTO ส่งมา (จาก Controller/User Login) ให้ใช้ค่านั้น
        // ถ้าไม่ส่งมา ให้ไปดู Default ของลูกค้า
        $finalSalespersonId = $dto->salespersonId;
        if (!$finalSalespersonId) {
            $customer = Customer::find($dto->customerId);
            $finalSalespersonId = $customer ? $customer->default_salesperson_id : null;
        }

        // 3. ✅ Financial Control (Credit Check)
        // คำนวณยอดรวมเบื้องต้นเพื่อเช็ควงเงิน
        $estimatedTotal = 0;
        foreach ($dto->items as $itemDto) {
            $product = $products[$itemDto->productId] ?? null;
            if ($product) {
                $estimatedTotal += $product->price * $itemDto->quantity;
            }
        }

        // ตรวจสอบวงเงิน (เฉพาะถ้ามีการ Confirm ทันที หรือแล้วแต่นโยบายว่าจะเช็คตั้งแต่ Draft ไหม)
        // ในที่นี้เช็คเลยเพื่อความปลอดภัย
        if ($estimatedTotal > 0) {
            $this->creditCheckService->canPlaceOrder($dto->customerId, $estimatedTotal);
        }

        return DB::transaction(function () use ($dto, $products, $finalSalespersonId) {

            // 4. Create Aggregate Root with Context
            // ✅ [ปรับปรุง] ส่ง companyId, warehouseId และ salespersonId
            $order = new Order(
                customerId: $dto->customerId,
                companyId: $dto->companyId,
                warehouseId: $dto->warehouseId,
                salespersonId: $finalSalespersonId // ✅ ส่งค่าที่ Resolve แล้ว
            );

            // 5. Add Items
            foreach ($dto->items as $itemDto) {
                $product = $products[$itemDto->productId] ?? null;

                if (!$product) {
                    throw new Exception("Product ID {$itemDto->productId} not found in catalog.");
                }

                $order->addItem(
                    productId: $product->id,
                    productName: $product->name,
                    price: $product->price,
                    quantity: $itemDto->quantity
                );
            }

            // 6. Update Details
            $order->updateDetails(
                customerId: $dto->customerId,
                note: $dto->note ?? null,
                paymentTerms: $dto->paymentTerms ?? null
            );

            // 7. Confirm Order (Optional)
            if ($dto->confirmOrder) {
                $order->confirm();
            }

            // 8. Save Aggregate
            $this->orderRepository->save($order);

            // 9. Auto Log & Events
            CommunicationMessage::create([
                'user_id' => Auth::id(),
                'body' => "Order Created (สร้างใบสั่งขาย) #{$order->getOrderNumber()}",
                'type' => 'notification',
                'model_type' => 'sales_order',
                'model_id' => $order->getId()
            ]);

            // 10. Dispatch Event ถ้า Confirm แล้ว
            if ($order->getStatus() === OrderStatus::Confirmed) {

                // [Day 4 Update] สร้าง Snapshot DTO เพื่อส่งไปกับ Event
                // แปลง OrderItems เป็น DTO
                $itemsSnapshot = $order->getItems()->map(function ($item) {
                    return new OrderItemSnapshotDto(
                        productId: $item->productId,
                        productName: $item->productName,
                        quantity: $item->quantity,
                        unitPrice: $item->unitPrice
                    );
                })->toArray();

                // สร้าง Order Snapshot
                $orderSnapshot = new OrderSnapshotDto(
                    orderId: $order->getId(),
                    orderNumber: $order->getOrderNumber(),
                    customerId: $order->getCustomerId(),
                    companyId: $order->getCompanyId(),
                    warehouseId: $order->getWarehouseId(),
                    items: $itemsSnapshot,
                    note: $order->getNote()
                );

                // ส่งข้อมูลครบชุดไปที่ Event (Logistics จะได้ไม่ต้อง Query กลับมา)
                OrderConfirmed::dispatch($order->getId(), $orderSnapshot);
            }

            return $order;
        });
    }
}
