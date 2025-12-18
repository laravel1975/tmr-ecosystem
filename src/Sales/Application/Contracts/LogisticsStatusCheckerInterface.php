<?php

namespace TmrEcosystem\Sales\Application\Contracts;

interface LogisticsStatusCheckerInterface
{
    /**
     * ตรวจสอบว่ากระบวนการเบิกสินค้า (Picking) เริ่มต้นไปแล้วหรือยัง
     * ถ้าเริ่มแล้ว ไม่ควรอนุญาตให้แก้ไข Order
     */
    public function isPickingStarted(string $orderId): bool;
}
