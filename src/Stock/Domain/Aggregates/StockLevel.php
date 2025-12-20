<?php

namespace TmrEcosystem\Stock\Domain\Aggregates;

use DateTimeImmutable;
use Exception;
use TmrEcosystem\Stock\Domain\Events\StockLevelUpdated;
use TmrEcosystem\Stock\Domain\Events\StockReserved; // New Event

class StockLevel
{
    private string $id;
    private string $inventoryItemId;
    private string $warehouseId;
    private string $locationId;
    private float $quantityOnHand;
    private float $quantitySoftReserved;
    private float $quantityHardReserved; // ✅ เพิ่ม Hard Reserve แยกชัดเจน

    // Tracking reservations to handle expiration (Simplified)
    private array $softReservations = [];

    public function __construct(
        string $id,
        string $inventoryItemId,
        string $warehouseId,
        string $locationId,
        float $quantityOnHand = 0,
        float $quantitySoftReserved = 0,
        float $quantityHardReserved = 0
    ) {
        $this->id = $id;
        $this->inventoryItemId = $inventoryItemId;
        $this->warehouseId = $warehouseId;
        $this->locationId = $locationId;
        $this->quantityOnHand = $quantityOnHand;
        $this->quantitySoftReserved = $quantitySoftReserved;
        $this->quantityHardReserved = $quantityHardReserved;
    }

    // --- Domain Logic ---

    public function getAvailableQuantity(): float
    {
        return $this->quantityOnHand - $this->quantitySoftReserved - $this->quantityHardReserved;
    }

    /**
     * Soft Reserve: จองชั่วคราว (เช่น รอชำระเงิน)
     * มี TTL ถ้าเกินเวลาต้อง Release คืนได้ (ผ่าน Scheduled Job)
     */
    public function reserveSoft(float $amount, string $referenceId, ?DateTimeImmutable $expiresAt = null): void
    {
        if ($amount <= 0) {
            throw new Exception("Reservation amount must be positive.");
        }

        if ($this->getAvailableQuantity() < $amount) {
            throw new Exception("Insufficient stock available for reservation.");
        }

        $this->quantitySoftReserved += $amount;

        // ในระบบจริงควรเก็บ $softReservations ลง Table แยก (stock_reservations)
        // เพื่อทำ TTL Cleanup แต่ใน Aggregate นี้เรา update state รวม

        // Emit Event
        // record(new StockReserved($this->id, $amount, 'SOFT', $referenceId));
    }

    /**
     * Hard Reserve: ล็อคเพื่อ Picking (เปลี่ยนจาก Soft -> Hard)
     * หรือจองตรงๆ สำหรับ Backorder ที่ของเข้าแล้ว
     */
    public function commitToHardReserve(float $amount): void
    {
        // กรณี convert จาก Soft Reserve
        if ($this->quantitySoftReserved >= $amount) {
            $this->quantitySoftReserved -= $amount;
            $this->quantityHardReserved += $amount;
        }
        // กรณีจองใหม่เลย (ต้องเช็ค Available)
        elseif ($this->getAvailableQuantity() >= $amount) {
            $this->quantityHardReserved += $amount;
        } else {
            throw new Exception("Cannot commit stock. Insufficient availability.");
        }
    }

    /**
     * Issue Stock: ตัดของจริงเมื่อ Shipment
     */
    public function issueStock(float $amount): void
    {
        // ตัดจาก Hard Reserve ก่อน (ปกติควรเป็น Flow นี้)
        if ($this->quantityHardReserved >= $amount) {
            $this->quantityHardReserved -= $amount;
            $this->quantityOnHand -= $amount;
        }
        // Fallback ตัดจาก Available (ถ้าไม่มี Hard Reserve)
        elseif ($this->getAvailableQuantity() >= $amount) {
            $this->quantityOnHand -= $amount;
        } else {
            throw new Exception("Cannot issue stock. Insufficient quantity.");
        }

        if ($this->quantityOnHand < 0) {
             throw new Exception("Stock integrity violation: Negative stock.");
        }
    }

    /**
     * Release: ปล่อยของคืน (Cancel Order / Expired)
     */
    public function releaseSoftReservation(float $amount): void
    {
        $this->quantitySoftReserved = max(0, $this->quantitySoftReserved - $amount);
    }

    // ... Getters / Factory Methods ...

    public function getId(): string { return $this->id; }
    public function getQuantityOnHand(): float { return $this->quantityOnHand; }
    public function getQuantitySoftReserved(): float { return $this->quantitySoftReserved; }
    public function getQuantityHardReserved(): float { return $this->quantityHardReserved; }
    public function getLocationUuid(): string { return $this->locationId; }

    // Reconstitute from DB state
    public static function fromStorage(object $data): self
    {
        return new self(
            $data->uuid,
            $data->item_uuid,
            $data->warehouse_uuid,
            $data->location_uuid,
            (float) $data->quantity_on_hand,
            (float) ($data->quantity_soft_reserved ?? 0),
            (float) ($data->quantity_hard_reserved ?? 0) // New column
        );
    }
}
