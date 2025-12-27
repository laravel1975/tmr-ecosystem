<?php

namespace TmrEcosystem\Stock\Domain\Aggregates;

use DateTimeImmutable;
use Exception;
use Illuminate\Support\Str;

class StockLevel
{
    private string $id;
    private string $inventoryItemId;
    private string $warehouseId;
    private string $locationId;
    private float $quantityOnHand;
    private float $quantitySoftReserved;
    private float $quantityHardReserved;

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
     * ✅ Added: ใช้สำหรับ Flow 1 (Create Soft Reserve)
     * เพิ่มยอดจองแบบ Soft (ตัดจาก Available)
     */
    public function increaseSoftReserve(float $amount): void
    {
        if ($amount <= 0) {
            throw new Exception("Reservation amount must be positive.");
        }

        if ($this->getAvailableQuantity() < $amount) {
            throw new Exception("Insufficient stock available for reservation.");
        }

        $this->quantitySoftReserved += $amount;
    }

    /**
     * ✅ Added: ใช้สำหรับ Flow 3 (Promote to Hard Reserve)
     * ย้ายยอดจาก Soft -> Hard (ยอดคงเหลือรวมเท่าเดิม แต่สถานะเปลี่ยน)
     */
    public function convertSoftToHard(float $amount): void
    {
        if ($amount <= 0) {
            throw new Exception("Amount must be positive.");
        }

        // ตรวจสอบว่ามียอด Soft Reserve พอให้เปลี่ยนไหม
        // (ใช้ epsilon สำหรับ float comparison เพื่อความปลอดภัย)
        if (($this->quantitySoftReserved + 0.00001) < $amount) {
             throw new Exception("Insufficient soft reserved stock to convert. Held: {$this->quantitySoftReserved}, Required: {$amount}");
        }

        $this->quantitySoftReserved -= $amount;
        $this->quantityHardReserved += $amount;

        // ป้องกันเศษติดลบจาก floating point
        if ($this->quantitySoftReserved < 0) $this->quantitySoftReserved = 0;
    }

    /**
     * ใช้สำหรับตัดของจริงออกจากคลัง (Shipment)
     */
    public function issueStock(float $amount): void
    {
        // ตัดจาก Hard Reserve ก่อน
        if ($this->quantityHardReserved >= $amount) {
            $this->quantityHardReserved -= $amount;
            $this->quantityOnHand -= $amount;
        }
        // Fallback ตัดจาก Available (กรณีด่วนไม่ได้จอง)
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
     * ✅ [Fix] เพิ่ม Method นี้เพื่อให้ ShipmentController เรียกใช้งานได้
     * ตัดสต็อกสำหรับการจัดส่ง (Shipment) โดยเรียกใช้ Logic เดียวกับ issueStock
     */
    public function shipReserved(float $amount): void
    {
        $this->issueStock($amount);
    }

    /**
     * ใช้สำหรับ Release Stock คืน (Order Cancel / Expired)
     */
    public function releaseSoftReservation(float $amount): void
    {
        $this->quantitySoftReserved = max(0, $this->quantitySoftReserved - $amount);
    }

    // --- Getters ---

    public function getId(): string { return $this->id; }
    public function getInventoryItemId(): string { return $this->inventoryItemId; }
    public function getWarehouseId(): string { return $this->warehouseId; }
    public function getLocationUuid(): string { return $this->locationId; }
    public function getQuantityOnHand(): float { return $this->quantityOnHand; }
    public function getQuantitySoftReserved(): float { return $this->quantitySoftReserved; }
    public function getQuantityHardReserved(): float { return $this->quantityHardReserved; }

    // --- Reconstitute ---

    public static function fromStorage(object $data): self
    {
        return new self(
            $data->uuid,
            $data->item_uuid,
            $data->warehouse_uuid,
            $data->location_uuid,
            (float) $data->quantity_on_hand,
            (float) ($data->quantity_soft_reserved ?? 0),
            (float) ($data->quantity_hard_reserved ?? 0)
        );
    }
}
