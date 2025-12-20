<?php

namespace TmrEcosystem\Sales\Domain\Aggregates;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use TmrEcosystem\Sales\Domain\Entities\OrderItem;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;
use Exception;
use TmrEcosystem\Shared\Domain\Enums\ReservationState;

class Order
{
    // Properties
    private string $id;
    private string $orderNumber;
    private string $customerId;
    private ?string $salespersonId;
    private string $companyId;
    private string $warehouseId;
    private OrderStatus $status;
    private Collection $items;
    private float $totalAmount;
    private string $note = '';
    private string $paymentTerms = 'immediate';
    private ReservationState $reservationStatus;

    // ✅ Constructor
    public function __construct(
        string $customerId,
        string $companyId = 'DEFAULT_COMPANY',
        string $warehouseId = 'DEFAULT_WAREHOUSE',
        ?string $salespersonId = null
    ) {
        $this->id = (string) Str::uuid();
        $this->customerId = $customerId;
        $this->companyId = $companyId;
        $this->warehouseId = $warehouseId;
        $this->salespersonId = $salespersonId;

        $this->status = OrderStatus::Draft;
        $this->items = collect([]);
        $this->totalAmount = 0;
        $this->orderNumber = 'DRAFT'; // ปกติควร Gen จาก Domain Service หรือ Repository
        $this->reservationStatus = ReservationState::NONE;
    }

    // --- Getters ---
    public function getId(): string
    {
        return $this->id;
    }
    public function getCustomerId(): string
    {
        return $this->customerId;
    }
    public function getCompanyId(): string
    {
        return $this->companyId;
    }
    public function getWarehouseId(): string
    {
        return $this->warehouseId;
    }
    public function getSalespersonId(): ?string
    {
        return $this->salespersonId;
    }
    public function getStatus(): OrderStatus
    {
        return $this->status;
    }
    public function getItems(): Collection
    {
        return $this->items;
    }
    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }
    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }
    public function getNote(): string
    {
        return $this->note;
    }
    public function getPaymentTerms(): string
    {
        return $this->paymentTerms;
    }

    // --- Domain Methods ---

    /**
     * เพิ่มสินค้าลงในออเดอร์
     */
    public function addItem(string $productId, string $productName, float $price, int $quantity, ?string $id = null, int $qtyShipped = 0): void
    {
        if (in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Completed])) {
            throw new Exception("Cannot modify finalized order.");
        }

        // ถ้าเป็นการแก้ไข Item เดิม และลดจำนวนลงต่ำกว่าที่ส่งไปแล้ว (กรณีนี้สร้างใหม่ตลอดจึงอาจไม่เจอ แต่กันไว้)
        if ($quantity < $qtyShipped) {
            throw new Exception("Cannot set quantity lower than shipped quantity.");
        }

        $item = new OrderItem(
            productId: $productId,
            productName: $productName,
            unitPrice: $price,
            quantity: $quantity,
            id: $id,
            qtyShipped: $qtyShipped
        );

        $this->items->push($item);
        $this->recalculateTotal();
    }

    // Called when User creates a draft or adds items
    public function requestReservation(): void
    {
        if ($this->reservationStatus === ReservationState::HARD_RESERVED) {
            return; // Already secured
        }
        // State ใน Sales เป็นแค่ View, ของจริงอยู่ที่ Inventory
        $this->reservationStatus = ReservationState::SOFT_RESERVED;
    }

    // Called via Event Listener when Inventory confirms Hard Reserve
    public function confirmReservation(): void
    {
        $this->reservationStatus = ReservationState::HARD_RESERVED;
    }

    // Called via Event Listener when Inventory says "Expired"
    public function markReservationExpired(): void
    {
        if ($this->status === OrderStatus::Confirmed) {
            // Critical Business Rule: ถ้า Order Confirm แล้ว แต่ Reservation หลุด
            // ต้องแจ้งเตือน Staff ด่วน (Overselling Risk)
        }
        $this->reservationStatus = ReservationState::EXPIRED;
    }

    /**
     * ล้างรายการสินค้าทั้งหมด (สำหรับกรณี Reset ตะกร้าก่อนบันทึกใหม่)
     */
    public function clearItems(): void
    {
        if (in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Completed])) {
            throw new Exception("Cannot clear items of finalized order.");
        }
        $this->items = collect([]);
        $this->recalculateTotal();
    }

    /**
     * อัปเดตรายละเอียดทั่วไปของออเดอร์
     */
    public function updateDetails(string $customerId, ?string $note, ?string $paymentTerms): void
    {
        $this->customerId = $customerId;
        $this->note = $note ?? '';
        $this->paymentTerms = $paymentTerms ?? 'immediate';
    }

    /**
     * ยืนยันออเดอร์ (เปลี่ยนสถานะเป็น Confirmed)
     */
    public function confirm(): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw new Exception("Order already confirmed or cancelled.");
        }
        if ($this->items->isEmpty()) {
            throw new Exception("Cannot confirm empty order.");
        }
        $this->status = OrderStatus::Confirmed;
    }

    /**
     * ยกเลิกออเดอร์
     */
    public function cancel(): void
    {
        if ($this->status === OrderStatus::Completed) {
            throw new Exception("Cannot cancel completed order.");
        }
        $this->status = OrderStatus::Cancelled;
    }

    /**
     * อัปเดตสถานะการจัดส่งของแต่ละ Item (เรียกจาก Logistics Integration)
     */
    public function updateItemShipmentStatus(string $itemId, int $totalQtyShipped): void
    {
        $item = $this->items->first(fn($i) => $i->id === $itemId);

        if ($item) {
            // เรียก method updateShippedQty ใน Entity OrderItem
            $item->updateShippedQty($totalQtyShipped);
        }

        // คำนวณสถานะภาพรวมใหม่ (Partially Shipped / Completed)
        $this->reassessStatus();
    }

    /**
     * คำนวณยอดรวมใหม่
     */
    private function recalculateTotal(): void
    {
        $this->totalAmount = $this->items->sum(fn($item) => $item->total());
    }

    /**
     * ประเมินสถานะของ Order ใหม่ตามยอดจัดส่ง
     */
    private function reassessStatus(): void
    {
        if ($this->items->isEmpty()) return;

        $allFullyShipped = $this->items->every(fn(OrderItem $item) => $item->isFullyShipped());
        $someShipped = $this->items->contains(fn(OrderItem $item) => $item->qtyShipped > 0);

        if ($allFullyShipped) {
            $this->status = OrderStatus::Completed;
        } elseif ($someShipped) {
            $this->status = OrderStatus::PartiallyShipped;
        } else {
            // ยังคงสถานะเดิม (เช่น Confirmed) ถ้ายังไม่ส่งเลย
            // แต่ต้องระวังไม่ให้ย้อนกลับไป Draft
            if ($this->status !== OrderStatus::Draft) {
                $this->status = OrderStatus::Confirmed;
            }
        }
    }

    /**
     * Rehydrate Object จาก Database
     */
    public static function reconstitute(
        string $id,
        string $orderNumber,
        string $customerId,
        string $companyId,
        string $warehouseId,
        ?string $salespersonId,
        string $statusString,
        float $totalAmount,
        iterable $itemsData,
        string $note = '',
        string $paymentTerms = 'immediate'
    ): self {
        // 1. เรียก Constructor หลัก
        $instance = new self($customerId, $companyId, $warehouseId, $salespersonId);

        // 2. Override ค่าที่ดึงจาก DB
        $instance->id = $id;
        $instance->orderNumber = $orderNumber;
        $instance->status = OrderStatus::from($statusString);
        $instance->totalAmount = $totalAmount;
        $instance->note = $note;
        $instance->paymentTerms = $paymentTerms;

        // 3. Map Items กลับมาเป็น Entity
        $instance->items = collect($itemsData)->map(fn($item) => OrderItem::fromStorage($item));

        return $instance;
    }

    /**
     * ✅ REFACTOR: Handle shipment update within Domain Boundary
     * เมธอดนี้จะถูกเรียกเมื่อ Logistics ส่ง Event กลับมาว่ามีการจัดส่งเกิดขึ้น
     * * @param array<string, int> $shippedItems Key=ItemId, Value=QuantityShipped
     */
    public function registerShipment(array $shippedQuantities): void
    {
        if ($this->status === OrderStatus::Draft || $this->status === OrderStatus::Cancelled) {
            throw new Exception("Cannot ship an order that is not confirmed.");
        }

        foreach ($shippedQuantities as $itemId => $qtyToAdd) {
            /** @var OrderItem|null $item */
            $item = $this->items->first(fn($i) => $i->id === $itemId);

            if (!$item) {
                continue;
            }

            // Invariant Check: ห้ามส่งเกินที่สั่ง
            $newTotalShipped = $item->qtyShipped + $qtyToAdd;
            if ($newTotalShipped > $item->quantity) {
                throw new Exception("Cannot ship more than ordered quantity for item {$item->productName}");
            }

            // Update state inside entity
            $item->updateShippedQty($newTotalShipped);
        }

        $this->reassessStatus();
    }
}
