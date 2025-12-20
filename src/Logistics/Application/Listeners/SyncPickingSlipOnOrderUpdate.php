<?php

namespace TmrEcosystem\Logistics\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Sales\Domain\Events\OrderUpdated;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlipItem;

class SyncPickingSlipOnOrderUpdate implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderUpdated $event): void
    {
        $orderId = $event->orderId;
        $orderSnapshot = $event->orderSnapshot; // ต้องแน่ใจว่า Event ส่ง Snapshot ล่าสุดมา

        Log::info("Logistics: Syncing Picking Slip for Updated Order {$orderId}");

        $pickingSlip = PickingSlip::where('order_id', $orderId)
            ->where('status', 'pending') // อัปเดตเฉพาะใบที่ยังไม่เริ่มหยิบ
            ->first();

        if (!$pickingSlip) {
            return; // ถ้าเริ่มหยิบไปแล้ว หรือไม่มีใบงาน ก็ไม่ต้องทำอะไร (ปล่อยให้เป็น Flow ของ Return/Change Request)
        }

        DB::transaction(function () use ($pickingSlip, $orderSnapshot) {
            // 1. ลบรายการเดิมทิ้ง (Simple Sync Strategy)
            PickingSlipItem::where('picking_slip_id', $pickingSlip->id)->delete();

            // 2. สร้างรายการใหม่จาก Snapshot ล่าสุด
            foreach ($orderSnapshot->items as $item) {
                 PickingSlipItem::create([
                    'picking_slip_id' => $pickingSlip->id,
                    'sales_order_item_id' => $item->id, // สำคัญ: ต้องตรงกับ UUID ใหม่ (ถ้ามีการ regen) หรือเก่า
                    'product_id' => $item->productId ?? $item->partNumber,
                    'product_name' => $item->productName,
                    'quantity_requested' => $item->quantity,
                    'quantity_picked' => 0,
                    'status' => 'pending'
                ]);
            }

            // 3. Update Slip Metadata (เช่น ยอดเงิน หรือ Note ถ้ามีเก็บ)
            $pickingSlip->touch();
        });

        Log::info("Logistics: Picking Slip {$pickingSlip->picking_number} synced successfully.");
    }
}
