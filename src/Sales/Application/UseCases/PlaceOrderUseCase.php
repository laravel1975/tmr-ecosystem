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
use TmrEcosystem\Sales\Domain\Services\CreditCheckService;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Sales\Domain\Events\OrderCreated; // ✅ ใช้ Event แทนการเรียก Communication โดยตรง
use TmrEcosystem\Sales\Application\DTOs\OrderSnapshotDto;
use TmrEcosystem\Sales\Application\DTOs\OrderItemSnapshotDto;
use TmrEcosystem\Sales\Application\Contracts\StockReservationInterface;
// ✅ เพิ่ม Interface นี้เพื่อตัดขาดจาก Eloquent Model ของ Customer Module
use TmrEcosystem\Sales\Application\Contracts\CustomerLookupInterface;

class PlaceOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductCatalogInterface $productCatalog,
        private CreditCheckService $creditCheckService,
        private StockReservationInterface $stockReservation,
        private CustomerLookupInterface $customerLookup // ✅ Inject Service Interface
    ) {}

    /**
     * @throws Exception
     */
    public function handle(CreateOrderDto $dto): Order
    {
        // 1. Prepare Data & Cross-Context Lookup
        // ❌ OLD: $customer = Customer::find($dto->customerId);
        // ✅ NEW: ดึงข้อมูลผ่าน Interface ส่งกลับเป็น DTO
        $customerData = $this->customerLookup->findById($dto->customerId);

        if (!$customerData) {
            throw new Exception("Customer not found.");
        }

        $productIds = array_map(fn($item) => $item->productId, $dto->items);
        $products = $this->productCatalog->getProductsByIds($productIds);

        // 2. Resolve Salesperson
        $finalSalespersonId = $dto->salespersonId;
        if (!$finalSalespersonId) {
            // ใช้ข้อมูลจาก DTO แทน Eloquent Model
            $finalSalespersonId = $customerData->defaultSalespersonId ?? null;
        }

        // 3. Financial Control
        $estimatedTotal = 0;
        foreach ($dto->items as $itemDto) {
            $product = $products[$itemDto->productId] ?? null;
            if ($product) {
                $estimatedTotal += $product->price * $itemDto->quantity;
            }
        }

        if ($estimatedTotal > 0) {
            $this->creditCheckService->canPlaceOrder($dto->customerId, $estimatedTotal);
        }

        return DB::transaction(function () use ($dto, $products, $finalSalespersonId, $customerData) {

            // 4. Create Aggregate Root
            $order = new Order(
                customerId: $dto->customerId,
                companyId: $dto->companyId,
                warehouseId: $dto->warehouseId,
                salespersonId: $finalSalespersonId
            );

            // ✅ SNAPSHOT LOGIC: บันทึกข้อมูลลูกค้า ณ เวลาสั่งซื้อ
            // ต้องเพิ่ม method setCustomerSnapshot ใน Order Aggregate หรือส่งผ่าน Constructor
            $order->setCustomerSnapshot([
                'name' => $customerData->name,
                'tax_id' => $customerData->taxId,
                'email' => $customerData->email,
                'phone' => $customerData->phone,
                'address' => $customerData->address, // ใช้เป็น Shipping Address ตั้งต้น
                'payment_terms' => $customerData->paymentTerms,
            ]);

            // 5. Add Items & Prepare for Reservation
            $reservationItems = [];

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

                $reservationItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $itemDto->quantity
                ];
            }

            // 6. Update Details
            $order->updateDetails(
                customerId: $dto->customerId,
                note: $dto->note ?? null,
                paymentTerms: $customerData->paymentTerms // ใช้ Payment Term จาก Customer Snapshot
            );

            // 7. Confirm Order & Reserve Stock
            if ($dto->confirmOrder) {
                $this->stockReservation->reserveItems(
                    orderId: $order->getId(),
                    items: $reservationItems,
                    warehouseId: $dto->warehouseId
                );

                $order->confirm();
            }

            // 8. Save Aggregate
            $this->orderRepository->save($order);

            // 9. Auto Log & Notification
            // ❌ OLD: CommunicationMessage::create(...) -> นี่คือ Eloquent Trap
            // ✅ NEW: Dispatch Event "OrderCreated" แล้วให้ Listener ฝั่ง Communication เป็นคนสร้าง Message เอง
            // หรือถ้ายังไม่มี Listener ให้ปล่อยไว้ก่อน แต่ห้ามเรียก Model ข้าม Context ตรงนี้

            // 10. Dispatch Events
            // Dispatch OrderCreated (สำหรับ Notification, Logging)
            OrderCreated::dispatch($order->getId());

            if ($order->getStatus() === OrderStatus::Confirmed) {
                // Prepare Snapshot DTO for Logistics
                $itemsSnapshot = $order->getItems()->map(fn($item) => new OrderItemSnapshotDto(
                    id: $item->getId(),
                    productId: $item->productId,
                    productName: $item->productName,
                    quantity: $item->quantity,
                    unitPrice: $item->unitPrice
                ))->toArray();

                $orderSnapshot = new OrderSnapshotDto(
                    orderId: $order->getId(),
                    orderNumber: $order->getOrderNumber(),
                    customerId: $order->getCustomerId(),
                    companyId: $order->getCompanyId(),
                    warehouseId: $order->getWarehouseId(),
                    items: $itemsSnapshot,
                    note: $order->getNote()
                );

                OrderConfirmed::dispatch($order->getId(), $orderSnapshot);
            }

            return $order;
        });
    }
}
