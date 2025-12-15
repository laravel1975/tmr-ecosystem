<?php

namespace TmrEcosystem\Logistics\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use TmrEcosystem\Logistics\Domain\Events\PickingSlipUpdated;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNoteItem; // สมมติว่ามี Model นี้

class SyncDeliveryNoteFromPicking implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(PickingSlipUpdated $event): void
    {
        $pickingSlip = $event->pickingSlip;

        // หา Delivery Note ที่คู่กัน (และยังไม่ได้ส่งของ)
        $deliveryNote = DeliveryNote::where('picking_slip_id', $pickingSlip->id)
            ->whereIn('status', ['wait_operation', 'ready_to_ship']) // สถานะที่ยังแก้ได้
            ->first();

        if (!$deliveryNote) return;

        DB::transaction(function () use ($deliveryNote, $pickingSlip) {
            // วิธีที่ง่ายและปลอดภัยที่สุดสำหรับการ Sync คือล้างรายการเดิมแล้วสร้างใหม่ตาม Picking Slip
            // (เฉพาะกรณีที่ยังไม่ได้เริ่มส่งของ)

            // 1. ลบรายการเดิมใน Delivery Note (ถ้ามี Relation items())
            // สมมติว่า DeliveryNote มี items() เป็น hasMany relationship
            $deliveryNote->items()->delete();

            // 2. สร้างรายการใหม่จาก Picking Slip Items
            $newItems = [];
            foreach ($pickingSlip->items as $pickItem) {
                $newItems[] = [
                    'delivery_note_id' => $deliveryNote->id,
                    'picking_slip_item_id' => $pickItem->id, // ถ้าเก็บ Ref ไว้
                    'product_id' => $pickItem->product_id,
                    'quantity' => $pickItem->quantity_requested, // ใช้ยอด Requested เป็นยอดตั้งต้นของ DO
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($newItems)) {
                // Insert แบบ Batch เพื่อประสิทธิภาพ
                // หมายเหตุ: ต้องแน่ใจว่าชื่อตารางถูกต้อง เช่น 'logistics_delivery_note_items'
                DB::table('logistics_delivery_note_items')->insert($newItems);
            }

            // อัพเดทสถานะ Picking Slip เป็น Synced ถ้าจำเป็น
            $deliveryNote->touch();
        });
    }
}
