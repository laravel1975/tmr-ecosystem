<?php

namespace TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent;

use TmrEcosystem\Stock\Domain\Aggregates\StockLevel as StockLevelAggregate;
use TmrEcosystem\Stock\Domain\Aggregates\StockMovement as StockMovementAggregate;
use TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\Models\StockLevelModel;
use TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\Models\StockMovementModel;

class StockLevelMapper
{
    public static function toDomain(StockLevelModel $model): StockLevelAggregate
    {
        // ✅ [Fix] ปรับ Parameter ให้ตรงกับ __construct ของ StockLevel Aggregate
        return new StockLevelAggregate(
            id: $model->uuid, // ใช้ UUID เป็น Domain ID
            inventoryItemId: $model->item_uuid,
            warehouseId: $model->warehouse_uuid,
            locationId: $model->location_uuid,
            quantityOnHand: (float) $model->quantity_on_hand,
            quantitySoftReserved: (float) ($model->quantity_soft_reserved ?? 0),
            quantityHardReserved: (float) ($model->quantity_hard_reserved ?? 0) // ใช้ hard_reserved แทน reserved
        );
    }

    public static function toPersistence(StockLevelAggregate $stockLevel): array
    {
        // ✅ [Fix] ใช้ Getter ที่มีอยู่จริงใน StockLevel Aggregate
        return [
            'uuid' => $stockLevel->getId(),
            // 'company_id' => $stockLevel->getCompanyId(), // ตัดออกเพราะใน Aggregate ไม่มี field นี้
            'item_uuid' => $stockLevel->getInventoryItemId(),
            'warehouse_uuid' => $stockLevel->getWarehouseId(),
            'location_uuid' => $stockLevel->getLocationUuid(),
            'quantity_on_hand' => $stockLevel->getQuantityOnHand(),
            // Map ค่า Reserved กลับไปลง DB (สมมติว่า DB ใช้ column quantity_hard_reserved หรือ quantity_reserved ตาม Migration)
            'quantity_reserved' => $stockLevel->getQuantityHardReserved(),
            'quantity_hard_reserved' => $stockLevel->getQuantityHardReserved(),
            'quantity_soft_reserved' => $stockLevel->getQuantitySoftReserved(),
        ];
    }

    public static function movementToPersistence(StockMovementAggregate $movement): StockMovementModel
    {
        // ใช้ Reflection เพื่อเข้าถึง Private Properties ของ POPO
        $reflection = new \ReflectionObject($movement);

        // Helper function to get property value
        $getVal = fn($name) => $reflection->getProperty($name)->getValue($movement);

        return new StockMovementModel([
            'uuid' => $getVal('uuid'),
            'stock_level_uuid' => $getVal('stockLevelUuid'),
            'user_id' => $getVal('userId'),
            'type' => $getVal('type'),
            'quantity_change' => $getVal('quantityChange'),
            'quantity_after_move' => $getVal('quantityAfterMove'),
            'reference' => $getVal('reference'),
        ]);
    }
}
