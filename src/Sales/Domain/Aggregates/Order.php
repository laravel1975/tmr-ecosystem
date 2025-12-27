<?php

namespace TmrEcosystem\Sales\Domain\Aggregates;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use TmrEcosystem\Sales\Domain\Entities\OrderItem;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;
use TmrEcosystem\Shared\Domain\Enums\ReservationState;
use Exception;

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
    private ?array $customerSnapshot = null;

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
    public function getId(): string { return $this->id; }
    public function getCustomerId(): string { return $this->customerId; }
    public function getCompanyId(): string { return $this->companyId; }
    public function getWarehouseId(): string { return $this->warehouseId; }
    public function getSalespersonId(): ?string { return $this->salespersonId; }
    public function getStatus(): OrderStatus { return $this->status; }
    public function getReservationStatus(): ReservationState { return $this->reservationStatus; }
    public function getItems(): Collection { return $this->items; }
    public function getTotalAmount(): float { return $this->totalAmount; }
    public function getOrderNumber(): string { return $this->orderNumber; }
    public function getNote(): string { return $this->note; }
    public function getPaymentTerms(): string { return $this->paymentTerms; }

    /**
     * ✅ Getter สำหรับดึง Snapshot (ใช้ตอน save ลง Repository หรือส่ง Event)
     */
    public function getCustomerSnapshot(): ?array
    {
        return $this->customerSnapshot;
    }

    /**
     * ✅ Method สำหรับบันทึกข้อมูลลูกค้า ณ เวลาสั่งซื้อ
     * ข้อมูลนี้จะถูก map ลง column 'customer_snapshot' ใน DB
     */
    public function setCustomerSnapshot(array $data): void
    {
        // สามารถเพิ่ม Validation ตรงนี้ได้ถ้าจำเป็น ว่าต้องมี key อะไรบ้าง
        $this->customerSnapshot = $data;
    }

    // --- Domain Methods: Item Management ---

    public function addItem(string $productId, string $productName, float $price, int $quantity, ?string $id = null, int $qtyShipped = 0): void
    {
        if (in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Completed])) {
            throw new Exception("Cannot modify finalized order.");
        }

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

        // Reset reservation status if items change (Simplified logic)
        if ($this->reservationStatus !== ReservationState::HARD_RESERVED) {
             $this->reservationStatus = ReservationState::NONE;
        }
    }

    public function clearItems(): void
    {
        if (in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Completed])) {
            throw new Exception("Cannot clear items of finalized order.");
        }
        $this->items = collect([]);
        $this->recalculateTotal();
        $this->reservationStatus = ReservationState::NONE;
    }

    public function updateDetails(string $customerId, ?string $note, ?string $paymentTerms): void
    {
        $this->customerId = $customerId;
        $this->note = $note ?? '';
        $this->paymentTerms = $paymentTerms ?? 'immediate';
    }

    // --- Domain Methods: Order Lifecycle ---

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

    public function cancel(): void
    {
        if ($this->status === OrderStatus::Completed) {
            throw new Exception("Cannot cancel completed order.");
        }
        $this->status = OrderStatus::Cancelled;
        $this->reservationStatus = ReservationState::RELEASED;
    }

    // --- Domain Methods: Reservation Management ---

    public function requestReservation(): void
    {
        if ($this->reservationStatus === ReservationState::HARD_RESERVED) {
            return;
        }
        // ใน Sales BC เป็นแค่การเปลี่ยน State เพื่อรอผลจาก Inventory BC
        $this->reservationStatus = ReservationState::SOFT_RESERVED;
    }

    public function markAsSoftReserved(): void
    {
        if ($this->reservationStatus === ReservationState::HARD_RESERVED) {
            return;
        }
        $this->reservationStatus = ReservationState::SOFT_RESERVED;
    }

    public function markAsHardReserved(): void
    {
        $this->reservationStatus = ReservationState::HARD_RESERVED;
    }

    public function handleReservationFailure(): void
    {
        if ($this->status === OrderStatus::Confirmed) {
            // ✅ FIX: ตอนนี้ OrderStatus::OnHold มีอยู่จริงแล้ว
            // ใช้เพื่อบอกว่า Order นี้มีปัญหาเรื่องสต็อก ต้องหยุดกระบวนการส่งต่อ
            $this->status = OrderStatus::OnHold;
        }

        $this->reservationStatus = ReservationState::EXPIRED;
    }

    // --- Domain Methods: Shipment ---

    public function updateItemShipmentStatus(string $itemId, int $totalQtyShipped): void
    {
        $item = $this->items->first(fn($i) => $i->id === $itemId);
        if ($item) {
            $item->updateShippedQty($totalQtyShipped);
        }
        $this->reassessStatus();
    }

    public function registerShipment(array $shippedQuantities): void
    {
        if ($this->status === OrderStatus::Draft || $this->status === OrderStatus::Cancelled) {
            throw new Exception("Cannot ship an order that is not confirmed.");
        }

        foreach ($shippedQuantities as $itemId => $qtyToAdd) {
            /** @var OrderItem|null $item */
            $item = $this->items->first(fn($i) => $i->id === $itemId);
            if (!$item) continue;

            $newTotalShipped = $item->qtyShipped + $qtyToAdd;
            if ($newTotalShipped > $item->quantity) {
                throw new Exception("Cannot ship more than ordered quantity for item {$item->productName}");
            }
            $item->updateShippedQty($newTotalShipped);
        }
        $this->reassessStatus();
    }

    // --- Internal Helpers ---

    private function recalculateTotal(): void
    {
        $this->totalAmount = $this->items->sum(fn($item) => $item->total());
    }

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
            if ($this->status !== OrderStatus::Draft) {
                $this->status = OrderStatus::Confirmed;
            }
        }
    }

    // --- Reconstitute ---

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
        string $paymentTerms = 'immediate',
        ?string $reservationStatus = null
    ): self {
        $instance = new self($customerId, $companyId, $warehouseId, $salespersonId);

        $instance->id = $id;
        $instance->orderNumber = $orderNumber;
        $instance->status = OrderStatus::from($statusString);
        $instance->totalAmount = $totalAmount;
        $instance->note = $note;
        $instance->paymentTerms = $paymentTerms;

        $instance->reservationStatus = $reservationStatus
            ? ReservationState::from($reservationStatus)
            : ReservationState::NONE;

        $instance->items = collect($itemsData)->map(fn($item) => OrderItem::fromStorage($item));

        return $instance;
    }
}
