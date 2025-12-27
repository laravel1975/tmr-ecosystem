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
use TmrEcosystem\Sales\Domain\Events\OrderCreated; // อย่าลืม Use ตัวนี้
use TmrEcosystem\Sales\Application\DTOs\OrderSnapshotDto;
use TmrEcosystem\Sales\Application\DTOs\OrderItemSnapshotDto;
use TmrEcosystem\Sales\Application\Contracts\StockReservationInterface;
use TmrEcosystem\Sales\Application\Contracts\CustomerLookupInterface;

class PlaceOrderUseCase
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductCatalogInterface $productCatalog,
        private CreditCheckService $creditCheckService,
        private StockReservationInterface $stockReservation,
        private CustomerLookupInterface $customerLookup
    ) {}

    /**
     * @throws Exception
     */
    public function handle(CreateOrderDto $dto): Order
    {
        // 1. Prepare Data & Cross-Context Lookup
        $customerData = $this->customerLookup->findById($dto->customerId);

        if (!$customerData) {
            throw new Exception("Customer not found.");
        }

        $productIds = array_map(fn($item) => $item->productId, $dto->items);
        $products = $this->productCatalog->getProductsByIds($productIds);

        // 2. Resolve Salesperson
        $finalSalespersonId = $dto->salespersonId;
        if (!$finalSalespersonId) {
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

            // 5. Snapshot Logic (Set to Entity)
            $order->setCustomerSnapshot([
                'name' => $customerData->name,
                'tax_id' => $customerData->taxId,
                'email' => $customerData->email,
                'phone' => $customerData->phone,
                'address' => $customerData->address,
                'shipping_address' => $customerData->shippingAddress,
                'payment_terms' => $customerData->paymentTerms,
            ]);

            // 6. Add Items
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

            // 7. Update Details
            $order->updateDetails(
                customerId: $dto->customerId,
                note: $dto->note ?? null,
                paymentTerms: $customerData->paymentTerms
            );

            // 8. Confirm Order & Reserve Stock
            if ($dto->confirmOrder) {
                $this->stockReservation->reserveItems(
                    orderId: $order->getId(),
                    items: $reservationItems,
                    warehouseId: $dto->warehouseId
                );

                $order->confirm();
            }

            // 9. Save Aggregate
            $this->orderRepository->save($order);

            // ✅ [เพิ่มบรรทัดนี้] Refresh ข้อมูลจาก DB เพื่อให้ได้ Item IDs ที่เพิ่งสร้างเสร็จ
            $order = $this->orderRepository->findById($order->getId());

            // ✅ PREPARE SNAPSHOT DTO HERE (สร้างครั้งเดียวใช้ได้ทั้ง OrderCreated และ OrderConfirmed)
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
                note: $order->getNote(),
                customerSnapshot: $order->getCustomerSnapshot() // ใส่ข้อมูล Customer Snapshot ด้วย
            );

            // 10. Dispatch Events
            // ✅ [Fix] ส่ง 2 argument: ID และ Snapshot DTO
            event(new OrderCreated($order->getId(), $orderSnapshot));

            if ($order->getStatus() === OrderStatus::Confirmed) {
                // ✅ ใช้ตัวแปร $orderSnapshot ตัวเดิมที่สร้างไว้ด้านบน
                OrderConfirmed::dispatch($order->getId(), $orderSnapshot);
            }

            return $order;
        });
    }
}
