<?php

namespace TmrEcosystem\Sales\Infrastructure\Integration;

use Illuminate\Support\Facades\DB;
use TmrEcosystem\Sales\Application\Contracts\LogisticsStatusCheckerInterface;

class LogisticsStatusService implements LogisticsStatusCheckerInterface
{
    public function isPickingStarted(string $orderId): bool
    {
        // ดึงสถานะของ Picking Slip ล่าสุดของ Order นี้
        $status = DB::table('logistics_picking_slips')
            ->where('order_id', $orderId)
            ->whereNull('deleted_at') // กรณีใช้ SoftDeletes
            ->value('status');

        if (!$status) {
            // ยังไม่มีเอกสาร = แก้ไขได้
            return false;
        }

        // ✅ REFACTOR: อนุญาตให้แก้ไขได้ถ้าสถานะยังเป็น pending หรือ draft
        // บล็อกเฉพาะเมื่อเริ่มกระบวนการจริง (assigned, picking, done, shipped)
        $blockingStatuses = ['assigned', 'in_progress', 'done', 'packed', 'shipped'];

        return in_array($status, $blockingStatuses);
    }

    public function isShipped(string $orderId): bool
    {
        return DB::table('logistics_shipments')
            ->where('order_id', $orderId)
            ->exists();
    }
}
