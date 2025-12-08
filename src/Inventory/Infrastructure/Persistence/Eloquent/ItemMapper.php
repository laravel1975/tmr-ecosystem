<?php

namespace TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent;

use TmrEcosystem\Inventory\Domain\Aggregates\Item as ItemAggregate;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\ItemModel;
// ✅ Import Value Objects
use TmrEcosystem\Inventory\Domain\ValueObjects\ItemCode;
use TmrEcosystem\Shared\Domain\ValueObjects\Money;

class ItemMapper
{
    /**
     * แปลงจาก Eloquent Model (Database) -> Domain Aggregate (POPO)
     * ใช้สำหรับดึงข้อมูลขึ้นมาใช้งาน (Hydration)
     */
    public static function toDomain(ItemModel $model): ItemAggregate
    {
        return new ItemAggregate(
            dbId: null, // เราใช้ UUID เป็น PK หลัก
            uuid: $model->uuid,
            companyId: $model->company_id,

            // ✅ Wrap Primitive Type ให้เป็น Value Object
            partNumber: new ItemCode($model->part_number),

            name: $model->name,

            // ✅ Map ID แทน String (จากการ Normalization)
            uomId: $model->uom_id,
            categoryId: $model->category_id,

            // ✅ Wrap Primitive Type ให้เป็น Value Object
            averageCost: new Money((float) $model->average_cost),

            description: $model->description
        );
    }

    /**
     * แปลงจาก Domain Aggregate (POPO) -> Array
     * ใช้สำหรับบันทึกลง Database (Persistence)
     */
    public static function toPersistence(ItemAggregate $item): array
    {
        return [
            'uuid' => $item->uuid(),
            'company_id' => $item->companyId(),

            // ✅ ดึงค่า Primitive ออกมาจาก Value Object (Unwrap)
            'part_number' => $item->partNumber(),

            'name' => $item->name(),

            // ✅ Map ID (จากการ Normalization)
            'uom_id' => $item->uomId(),
            'category_id' => $item->categoryId(),

            // ✅ ดึงค่า Primitive ออกมาจาก Value Object (Unwrap)
            'average_cost' => $item->averageCost(),

            'description' => $item->description(),
        ];
    }
}
