<?php

namespace TmrEcosystem\Sales\Infrastructure\Integration;

use TmrEcosystem\Sales\Application\Contracts\LogisticsStatusCheckerInterface;
// เรียกใช้ Model ของ Logistics เฉพาะใน Layer นี้เท่านั้น (Integration)
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;

class LogisticsStatusService implements LogisticsStatusCheckerInterface
{
    public function isPickingStarted(string $orderId): bool
    {
        // ตรวจสอบข้อมูลในตาราง Picking Slip ของ Logistics
        $pickingSlip = PickingSlip::where('order_id', $orderId)->first();

        if (!$pickingSlip) {
            return false;
        }

        // สถานะที่ถือว่าเริ่มกระบวนการไปแล้ว
        $lockedStatuses = ['in_progress', 'done', 'packed', 'shipped'];

        return in_array($pickingSlip->status, $lockedStatuses);
    }
}
