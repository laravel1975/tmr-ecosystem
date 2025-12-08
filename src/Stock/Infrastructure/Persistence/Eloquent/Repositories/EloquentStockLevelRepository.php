<?php

namespace TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\Repositories;

use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use TmrEcosystem\Stock\Domain\Aggregates\StockLevel;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\Models\StockLevelModel;
use TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\StockLevelMapper;
use TmrEcosystem\Stock\Application\DTOs\StockLevelIndexData;

class EloquentStockLevelRepository implements StockLevelRepositoryInterface
{
    public function nextUuid(): string
    {
        return (string) Str::uuid();
    }

    public function findByLocation(string $itemUuid, string $locationUuid, string $companyId): ?StockLevel
    {
        $model = StockLevelModel::where('item_uuid', $itemUuid)
            ->where('location_uuid', $locationUuid)
            ->where('company_id', $companyId)
            ->first();

        if (is_null($model)) return null;
        return StockLevelMapper::toDomain($model);
    }

    public function save(StockLevel $stockLevel, array $movements): void
    {
        DB::transaction(function () use ($stockLevel, $movements) {
            $levelData = StockLevelMapper::toPersistence($stockLevel);

            StockLevelModel::updateOrCreate(
                ['uuid' => $stockLevel->uuid()],
                $levelData
            );

            foreach ($movements as $movement) {
                $movementModel = StockLevelMapper::movementToPersistence($movement);
                $movementModel->save();
            }
        });
    }

    public function getPaginatedList(string $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = StockLevelModel::query()
            ->join('inventory_items', 'stock_levels.item_uuid', '=', 'inventory_items.uuid')
            ->join('warehouses', 'stock_levels.warehouse_uuid', '=', 'warehouses.uuid')
            ->join('warehouse_storage_locations', 'stock_levels.location_uuid', '=', 'warehouse_storage_locations.uuid')
            ->where('stock_levels.company_id', $companyId)
            ->select(
                'stock_levels.uuid as stock_level_uuid',
                'stock_levels.item_uuid',      // ✅ Select UUID
                'stock_levels.warehouse_uuid', // ✅ Select UUID
                'stock_levels.location_uuid',  // ✅ Select UUID
                'stock_levels.quantity_on_hand',
                'stock_levels.quantity_reserved',
                'stock_levels.quantity_soft_reserved',
                'inventory_items.name as item_name',
                'inventory_items.part_number as item_part_number',
                'warehouses.name as warehouse_name',
                'warehouses.code as warehouse_code',
                'warehouse_storage_locations.code as location_code',
                'warehouse_storage_locations.type as location_type'
            );

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('inventory_items.part_number', 'like', "%{$search}%")
                    ->orWhere('inventory_items.name', 'like', "%{$search}%")
                    ->orWhere('warehouses.code', 'like', "%{$search}%")
                    ->orWhere('warehouse_storage_locations.code', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['warehouse_uuid']) && $filters['warehouse_uuid'] !== 'all') {
            $query->where('stock_levels.warehouse_uuid', $filters['warehouse_uuid']);
        }

        $paginatedResults = $query->paginate(15)->withQueryString();

        $paginatedResults->setCollection(
            $paginatedResults->getCollection()->map(function ($result) { // 👈 ตัวแปรคือ $result
                $onHand = (float) $result->quantity_on_hand;
                $hardReserved = (float) $result->quantity_reserved;
                $softReserved = (float) ($result->quantity_soft_reserved ?? 0);

                $available = $onHand - ($hardReserved + $softReserved);

                return new StockLevelIndexData(
                    stock_level_uuid: $result->stock_level_uuid,

                    // ✅ FIX: เปลี่ยนจาก $data->... เป็น $result->...
                    item_uuid: $result->item_uuid ?? '',
                    warehouse_uuid: $result->warehouse_uuid ?? '',
                    location_uuid: $result->location_uuid ?? '',

                    item_part_number: $result->item_part_number,
                    item_name: $result->item_name,
                    warehouse_code: $result->warehouse_code,
                    warehouse_name: $result->warehouse_name,

                    // ✅ FIX: เปลี่ยนจาก $data->... เป็น $result->...
                    location_code: $result->location_code ?? 'N/A',
                    location_type: $result->location_type ?? 'UNKNOWN',

                    quantity_on_hand: $onHand,
                    quantity_reserved: $hardReserved,
                    quantity_soft_reserved: $softReserved,
                    quantity_available: $available
                );
            })
        );

        return $paginatedResults;
    }

    /**
     * ✅ [เพิ่มใหม่] ดึงรายการสต็อกที่ "หยิบได้" ทั้งหมดของสินค้านั้น
     * โดยเรียงลำดับความสำคัญ: PICKING -> BULK -> อื่นๆ -> GENERAL
     */
    public function findPickableStocks(string $itemUuid, string $warehouseUuid): \Illuminate\Support\Collection
    {
        $models = \TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\Models\StockLevelModel::query()
            ->join('warehouse_storage_locations', 'stock_levels.location_uuid', '=', 'warehouse_storage_locations.uuid')
            ->where('stock_levels.item_uuid', $itemUuid)
            ->where('stock_levels.warehouse_uuid', $warehouseUuid)
            ->where('stock_levels.quantity_on_hand', '>', 0) // ต้องมีของ
            ->where('warehouse_storage_locations.is_active', true)
            // กรอง Type ที่ห้ามหยิบ
            ->whereNotIn('warehouse_storage_locations.type', ['DAMAGED', 'RETURN', 'INBOUND'])

            // 🔥 เรียงลำดับความสำคัญ: PICKING มาก่อน, GENERAL ไว้หลังสุด
            ->orderByRaw("
                CASE
                    WHEN warehouse_storage_locations.type = 'PICKING' THEN 1
                    WHEN warehouse_storage_locations.code = 'GENERAL' THEN 99
                    ELSE 2
                END ASC
            ")
            // FIFO (First-In, First-Out) ตามวันที่สร้าง
            ->orderBy('stock_levels.created_at', 'asc')

            ->select('stock_levels.*')
            ->get();

        return $models->map(fn($model) => \TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\StockLevelMapper::toDomain($model));
    }

    /**
     * ✅ [เพิ่มใหม่] ค้นหา StockLevel ที่มี Soft Reserve > 0 เพื่อทำการคืนของ
     */
    public function findWithSoftReserve(string $itemUuid, string $warehouseUuid): iterable
    {
        $models = StockLevelModel::query()
            ->where('item_uuid', $itemUuid)
            ->where('warehouse_uuid', $warehouseUuid)
            ->where('quantity_soft_reserved', '>', 0) // หาเฉพาะที่มียอดจองค้าง
            ->get();

        // แปลงจาก Eloquent Model กลับเป็น Domain Aggregate
        return $models->map(fn($model) => StockLevelMapper::toDomain($model));
    }
}
