<?php

namespace TmrEcosystem\Sales\Infrastructure\Integration;

use Exception;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Sales\Application\Contracts\StockReservationInterface;
use TmrEcosystem\Stock\Application\UseCases\ReserveStockUseCase;
use TmrEcosystem\Stock\Application\UseCases\ReleaseStockUseCase; // ✅ Import UseCase ใหม่

class StockReservationService implements StockReservationInterface
{
    public function __construct(
        private ReserveStockUseCase $reserveStockUseCase,
        private ReleaseStockUseCase $releaseStockUseCase // ✅ Inject UseCase ใหม่
    ) {}

    public function reserveItems(string $orderId, array $items, string $warehouseId): void
    {
        $reservationItems = array_map(fn($item) => [
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity']
        ], $items);

        try {
            $this->reserveStockUseCase->handle($orderId, $reservationItems, $warehouseId);
        } catch (Exception $e) {
            throw new Exception("Stock reservation failed: " . $e->getMessage());
        }
    }

    // ✅ Implement method นี้
    public function releaseReservation(string $orderId, array $items, string $warehouseId): void
    {
        $releaseItems = array_map(fn($item) => [
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity']
        ], $items);

        try {
            // เรียกข้าม Module (In-process call for Modular Monolith)
            $this->releaseStockUseCase->handle($orderId, $releaseItems, $warehouseId);
        } catch (Exception $e) {
            // กรณี Release ไม่ผ่าน (เช่น หาของจองไม่เจอ) ให้ Log ไว้แต่ไม่ควรขัดขวางการ Cancel Order
            Log::error("Failed to release stock reservation for order {$orderId}: " . $e->getMessage());
        }
    }
}
