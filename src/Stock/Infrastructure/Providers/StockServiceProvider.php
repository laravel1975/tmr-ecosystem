<?php

namespace TmrEcosystem\Stock\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use TmrEcosystem\Sales\Domain\Events\OrderCancelled;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Stock\Infrastructure\Persistence\Eloquent\Repositories\EloquentStockLevelRepository;

// Event
// use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Sales\Domain\Events\OrderUpdated;
use TmrEcosystem\Stock\Application\Contracts\StockCheckServiceInterface;
use TmrEcosystem\Stock\Application\Listeners\ReleaseStockOnOrderCancelled;
// Listener
// use TmrEcosystem\Stock\Application\Listeners\ReserveStockOnOrderConfirmed;
use TmrEcosystem\Stock\Application\Listeners\SyncStockOnOrderUpdated;
use TmrEcosystem\Stock\Application\Services\StockCheckService;
use TmrEcosystem\Stock\Application\Services\StockPickingService;

class StockServiceProvider extends ServiceProvider
{
    /**
     * (3) ลงทะเบียนการ "Bind"
     */
    public function register(): void
    {
        // ✅ หัวใจสำคัญ: บอก Laravel ว่าถ้าใครขอ Interface นี้ ให้ส่ง Eloquent Repo นี้ไปให้
        $this->app->bind(
            StockLevelRepositoryInterface::class,
            EloquentStockLevelRepository::class
        );

        $this->app->bind(
            StockCheckServiceInterface::class,
            StockCheckService::class
        );

        // ✅ Bind Service นี้ให้เรียกใช้ได้ (หรือเรียกใช้ direct class ก็ได้เพราะเป็น Concrete class)
        $this->app->singleton(StockPickingService::class);
    }

    /**
     * (4) "Boot" Bounded Context
     */
    public function boot(): void
    {
        // 1. Confirm
        // Event::listen(
        //     OrderConfirmed::class,
        //     ReserveStockOnOrderConfirmed::class
        // );

        // 2. Update
        Event::listen(
            OrderUpdated::class,
            SyncStockOnOrderUpdated::class
        );

        // 3. ✅ Cancel (เพิ่มส่วนนี้)
        Event::listen(
            OrderCancelled::class,
            ReleaseStockOnOrderCancelled::class
        );

        // (4A) บอก Laravel ให้โหลด Migrations จากที่นี่
        $this->loadMigrationsFrom(
            __DIR__ . '/../Persistence/database/migrations'
        );

        // (2. 👈 [ใหม่] เรียกฟังก์ชันโหลด Routes)
        $this->bootRoutes();
    }

    /**
     * (3. 👈 [ใหม่] ฟังก์ชันสำหรับโหลด Routes)
     */
    protected function bootRoutes(): void
    {
        $webRoutePath = __DIR__ . '/../../Presentation/Http/routes/stock.php';

        Route::middleware(['web', 'auth', 'verified'])
            ->prefix('stock') // (ใส่ /stock ให้อัตโนมัติ)
            ->name('stock.') // (ใส่ stock. ให้อัตโนมัติ)
            ->group(function () use ($webRoutePath) {
                if (file_exists($webRoutePath)) {
                    require $webRoutePath;
                }
            });
    }
}
