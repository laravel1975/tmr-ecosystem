<?php

namespace TmrEcosystem\Sales\Application\Contracts;

interface StockReservationInterface
{
    /**
     * จองสินค้า (Soft Reservation) สำหรับออเดอร์
     */
    public function reserveItems(string $orderId, array $items, string $warehouseId): void;

    /**
     * ยกเลิกการจอง (คืน Stock)
     * ✅ [Updated] เพิ่ม params $items และ $warehouseId
     * @param string $orderId
     * @param array $items Array of ['product_id' => string, 'quantity' => float]
     * @param string $warehouseId
     */
    public function releaseReservation(string $orderId, array $items, string $warehouseId): void;
}
