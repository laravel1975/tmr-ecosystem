<?php

namespace TmrEcosystem\Inventory\Application\Services;

use Illuminate\Support\Facades\DB;
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Inventory\Application\DTOs\PublicItemDto;

class ItemLookupService implements ItemLookupServiceInterface
{
    public function findByPartNumber(string $partNumber): ?PublicItemDto
    {
        $items = $this->fetchItems([$partNumber], 'part_number');
        return $items[0] ?? null;
    }

    /**
     * ✅ [FIXED] เพิ่มเมธอดนี้โดยใช้ Logic เดียวกับ findByPartNumber
     * (ใช้ fetchItems ดึงข้อมูลจาก DB โดยตรง ไม่ต้องพึ่ง Repository)
     */
    public function findByUuid(string $uuid): ?PublicItemDto
    {
        // ใช้ helper function ที่มีอยู่แล้ว โดยระบุ keyField เป็น 'uuid'
        $items = $this->fetchItems([$uuid], 'uuid');
        return $items[0] ?? null;
    }

    public function getByPartNumbers(array $partNumbers): array
    {
        if (empty($partNumbers)) return [];

        $dtos = $this->fetchItems($partNumbers, 'part_number');

        $result = [];
        foreach ($dtos as $dto) {
            $result[$dto->partNumber] = $dto;
        }
        return $result;
    }

    public function searchItems(string $search = '', array $includeIds = []): array
    {
        $query = DB::table('inventory_items')
            ->where('is_active', true)
            ->where('can_sell', true);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('part_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if (!empty($includeIds)) {
            $query->orWhereIn('part_number', $includeIds)
                ->orWhereIn('uuid', $includeIds);
        }

        // ดึงเฉพาะ ID ก่อนเพื่อประสิทธิภาพ
        $itemRecords = $query->orderBy('name')->limit(50)->get();
        $uuids = $itemRecords->pluck('uuid')->toArray();

        return $this->fetchItems($uuids, 'uuid');
    }

    /**
     * ✅ Helper Function: ดึงข้อมูล Items พร้อมรูปภาพ (Robust Logic)
     */
    private function fetchItems(array $keys, string $keyField): array
    {
        if (empty($keys)) return [];

        // 1. ดึงข้อมูลสินค้าพื้นฐาน
        $items = DB::table('inventory_items')
            ->join('inventory_uoms', 'inventory_items.uom_id', '=', 'inventory_uoms.id')
            ->whereIn("inventory_items.{$keyField}", $keys)
            ->select(
                'inventory_items.*',
                'inventory_uoms.symbol as uom_symbol',
                'inventory_uoms.name as uom_name'
            )
            ->get();

        if ($items->isEmpty()) return [];

        // 2. ดึงรูปภาพทั้งหมดของสินค้าเหล่านี้
        $uuids = $items->pluck('uuid')->toArray();
        $images = DB::table('inventory_item_images')
            ->whereIn('item_uuid', $uuids)
            ->orderBy('is_primary', 'desc') // เอา Primary ขึ้นก่อน
            ->orderBy('sort_order', 'asc')  // แล้วเรียงตามลำดับ
            ->get()
            ->groupBy('item_uuid');

        // 3. จับคู่และสร้าง DTO
        return $items->map(function ($item) use ($images) {
            $uomString = $item->uom_symbol ?? $item->uom_name ?? 'N/A';

            // ✅ Logic เลือกรูป: เอาตัวแรกสุดที่เจอ (ซึ่งเรา sort primary ไว้บนสุดแล้ว)
            $firstImage = isset($images[$item->uuid]) ? $images[$item->uuid]->first() : null;
            $imageUrl = $firstImage ? asset('storage/' . $firstImage->path) : null;

            return new PublicItemDto(
                uuid: $item->uuid,
                partNumber: $item->part_number,
                name: $item->name,
                price: (float) $item->average_cost,
                uom: $uomString,
                imageUrl: $imageUrl // ✅ ได้รูปแน่นอนถ้ามีใน DB
            );
        })->toArray();
    }
}
