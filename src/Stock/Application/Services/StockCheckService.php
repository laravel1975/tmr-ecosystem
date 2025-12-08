<?php

namespace TmrEcosystem\Stock\Application\Services;

use Illuminate\Support\Facades\DB;
use TmrEcosystem\Stock\Application\Contracts\StockCheckServiceInterface;

class StockCheckService implements StockCheckServiceInterface
{
    /**
     * ✅ ตรวจสอบยอดที่ "พร้อมขาย" (Available to Promise - ATP)
     * ใช้โดย: Sales Module (ตอนสร้าง/ยืนยันออเดอร์)
     * * สูตร: (Sum(On Hand) - Sum(Reserved) - Sum(Soft Reserved))
     * เงื่อนไข: เฉพาะ Location ที่ดี (ไม่รวม Damaged/Return)
     */
    public function checkAvailability(string $partNumber, string $warehouseId): float
    {
        $query = DB::table('stock_levels')
            ->join('inventory_items', 'stock_levels.item_uuid', '=', 'inventory_items.uuid')
            ->join('warehouse_storage_locations', 'stock_levels.location_uuid', '=', 'warehouse_storage_locations.uuid')

            // 1. Filter ตาม Item และ Warehouse
            ->where('inventory_items.part_number', $partNumber)
            ->where('stock_levels.warehouse_uuid', $warehouseId)

            // 2. ✅ Standard Filters (กรองข้อมูลขยะ/ของเสีย)
            ->whereNull('stock_levels.deleted_at') // ไม่เอาที่ลบแล้ว
            ->where('warehouse_storage_locations.is_active', true) // ไม่เอา Location ที่ปิดตาย
            ->whereNotIn('warehouse_storage_locations.type', ['DAMAGED', 'RETURN']); // ไม่เอาของเสีย/ของรอคืน

        // 3. ✅ ใช้ SUM แทน first() เพื่อรวมยอดจากทุก Location (A-1-1 + GENERAL + ...)
        $available = $query->sum(DB::raw('quantity_on_hand - quantity_reserved - quantity_soft_reserved'));

        return (float) max(0, $available); // ห้ามคืนค่าติดลบ
    }

    /**
     * ตรวจสอบหลายรายการพร้อมกัน (Batch)
     */
    public function checkAvailabilityBatch(array $partNumbers, string $warehouseId): array
    {
        if (empty($partNumbers)) return [];

        $results = DB::table('stock_levels')
            ->join('inventory_items', 'stock_levels.item_uuid', '=', 'inventory_items.uuid')
            ->join('warehouse_storage_locations', 'stock_levels.location_uuid', '=', 'warehouse_storage_locations.uuid')

            ->whereIn('inventory_items.part_number', $partNumbers)
            ->where('stock_levels.warehouse_uuid', $warehouseId)

            // ✅ Standard Filters
            ->whereNull('stock_levels.deleted_at')
            ->where('warehouse_storage_locations.is_active', true)
            ->whereNotIn('warehouse_storage_locations.type', ['DAMAGED', 'RETURN'])

            ->groupBy('inventory_items.part_number') // Group ตามสินค้า
            ->select(
                'inventory_items.part_number',
                // ✅ ใช้ SUM รวมยอด
                DB::raw('SUM(quantity_on_hand - quantity_reserved - quantity_soft_reserved) as available')
            )
            ->get();

        // Prepare Result Map
        $map = [];
        foreach ($partNumbers as $pn) $map[$pn] = 0.0; // Default 0

        foreach ($results as $row) {
            $map[$row->part_number] = (float) max(0, $row->available);
        }

        return $map;
    }

    /**
     * ✅ [เพิ่มใหม่] ตรวจสอบยอดพร้อมขายโดยใช้ Item UUID (เร็วกว่าใช้ Part Number)
     * เพื่อแก้ Error: Class contains 1 abstract method
     */
    public function getAvailableQuantity(string $itemId, string $warehouseId): float
    {
        $query = DB::table('stock_levels')
            // Join เพื่อเช็คสถานะ Location (ดี/เสีย)
            ->join('warehouse_storage_locations', 'stock_levels.location_uuid', '=', 'warehouse_storage_locations.uuid')

            // Filter
            ->where('stock_levels.item_uuid', $itemId)
            ->where('stock_levels.warehouse_uuid', $warehouseId)

            // Standard Filters (กรองของเสีย/Location ปิดตาย)
            ->whereNull('stock_levels.deleted_at')
            ->where('warehouse_storage_locations.is_active', true)
            ->whereNotIn('warehouse_storage_locations.type', ['DAMAGED', 'RETURN']);

        // คำนวณ: พร้อมขาย = มีอยู่จริง - จองแล้ว - กำลังจะจอง
        $available = $query->sum(DB::raw('quantity_on_hand - quantity_reserved - quantity_soft_reserved'));

        return (float) max(0, $available);
    }

    /**
     * ✅ ดึงสรุปยอดสต็อก (Dashboard View)
     * ใช้โดย: Inventory (หน้า Product Detail)
     */
    public function getStockSummary(string $itemUuid, string $companyId): array
    {
        $result = DB::table('stock_levels')
            ->join('warehouse_storage_locations', 'stock_levels.location_uuid', '=', 'warehouse_storage_locations.uuid')

            ->where('stock_levels.item_uuid', $itemUuid)
            ->where('stock_levels.company_id', $companyId)

            // ✅ Standard Filters
            ->whereNull('stock_levels.deleted_at')
            ->where('warehouse_storage_locations.is_active', true)

            // หมายเหตุ: หน้า Dashboard อาจจะอยากเห็นของเสียด้วย (เพื่อบริหารจัดการ)
            // แต่ถ้าอยากโชว์แค่ "ของดี" ให้ uncomment บรรทัดล่าง
            // ->whereNotIn('warehouse_storage_locations.type', ['DAMAGED', 'RETURN'])

            ->selectRaw('
                SUM(quantity_on_hand) as on_hand,
                SUM(quantity_reserved + quantity_soft_reserved) as outgoing
            ')
            ->first();

        // Incoming (Future: ดึงจาก Purchase Order)
        $incoming = 0;

        return [
            'on_hand' => (float) ($result->on_hand ?? 0),
            'outgoing' => (float) ($result->outgoing ?? 0),
            'incoming' => (float) $incoming,
            'forecast' => (float) (($result->on_hand ?? 0) + $incoming - ($result->outgoing ?? 0))
        ];
    }
}
