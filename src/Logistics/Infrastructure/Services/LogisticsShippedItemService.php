<?php

namespace TmrEcosystem\Logistics\Infrastructure\Services;

use Illuminate\Support\Facades\DB;
use TmrEcosystem\Sales\Application\Contracts\ShippedItemProviderInterface;
use TmrEcosystem\Sales\Application\DTOs\ShippedItemDto;

class LogisticsShippedItemService implements ShippedItemProviderInterface
{
    public function getByPickingSlipId(string $pickingSlipId): array
    {
        // 1. Query จาก Table ของ Logistics (เจ้าของพื้นที่ทำเองได้ ไม่ผิด)
        $items = DB::table('logistics_picking_slip_items')
            ->where('picking_slip_id', $pickingSlipId)
            ->select('sales_order_item_id', 'quantity_picked')
            ->get();

        // 2. แปลงข้อมูลเป็น DTO ของ Sales (Mapping)
        return $items->map(function ($item) {
            return new ShippedItemDto(
                sales_order_item_id: $item->sales_order_item_id,
                quantity_picked: (float) $item->quantity_picked
            );
        })->toArray();
    }
}
