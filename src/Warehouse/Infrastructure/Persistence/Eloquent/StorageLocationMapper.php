<?php

namespace TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent;

use TmrEcosystem\Warehouse\Domain\Aggregates\StorageLocation;
use TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\StorageLocationModel;

class StorageLocationMapper
{
    public static function toDomain(StorageLocationModel $model): StorageLocation
    {
        return new StorageLocation(
            dbId: $model->id,
            uuid: $model->uuid,
            warehouseUuid: $model->warehouse_uuid,
            code: $model->code,
            barcode: $model->barcode,
            type: $model->type,
            description: $model->description,
            isActive: (bool) $model->is_active,
            // ✅ [NEW] Map ข้อมูลกลับเข้า Domai
            maxCapacity: $model->max_capacity,
            isFull: $model->is_full
        );
    }

    public static function toPersistence(StorageLocation $aggregate): array
    {
        return [
            'uuid' => $aggregate->uuid(),
            'warehouse_uuid' => $aggregate->warehouseUuid(),
            'code' => $aggregate->code(),
            'barcode' => $aggregate->barcode(),
            'type' => $aggregate->type(),
            'description' => $aggregate->description(),
            'is_active' => $aggregate->isActive(),
            // ✅ [NEW] Map ข้อมูลลง Database
            'max_capacity' => $aggregate->getMaxCapacity(),
            'is_full' => $aggregate->isFull(),
        ];
    }
}
