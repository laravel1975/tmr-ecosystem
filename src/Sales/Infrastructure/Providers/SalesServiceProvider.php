<?php

namespace TmrEcosystem\Sales\Infrastructure\Providers;

use Illuminate\Support\Facades\Event; // ✅ Import Event Facade
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use TmrEcosystem\Logistics\Domain\Events\DeliveryNoteCancelled;
use TmrEcosystem\Logistics\Infrastructure\Services\LogisticsShippedItemService;
use TmrEcosystem\Sales\Application\Contracts\LogisticsStatusCheckerInterface;
use TmrEcosystem\Sales\Application\Contracts\ShippedItemProviderInterface;
use TmrEcosystem\Sales\Application\Contracts\StockReservationInterface;
use TmrEcosystem\Sales\Application\Listeners\CancelOrderOnDeliveryFailure;
use TmrEcosystem\Sales\Domain\Repositories\OrderRepositoryInterface;
use TmrEcosystem\Sales\Domain\Services\ProductCatalogInterface;
use TmrEcosystem\Sales\Infrastructure\Integration\InventoryProductCatalog;
use TmrEcosystem\Sales\Infrastructure\Integration\LogisticsStatusService;
use TmrEcosystem\Sales\Infrastructure\Integration\StockReservationService;
use TmrEcosystem\Sales\Infrastructure\Persistence\EloquentOrderRepository;

/**
 * --- 1. Import Domain Repository Interfaces ---
 * (นี่คือ "สัญญาท" ที่เรากำหนดไว้ใน Domain Layer ว่าต้องทำอะไรได้บ้าง)
 */

class SalesServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Bind Repository
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);

        // Bind Product Service (Integration)
        $this->app->bind(ProductCatalogInterface::class, InventoryProductCatalog::class);

        $this->app->bind(ShippedItemProviderInterface::class, LogisticsShippedItemService::class);
        $this->app->bind(LogisticsStatusCheckerInterface::class, LogisticsStatusService::class);

        // ✅ Register Reservation Binding
        $this->app->bind(
            StockReservationInterface::class,
            StockReservationService::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {

        // โหลดไฟล์ Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Persistence/database/migrations');

        // 👈 2. เรียกฟังก์ชัน bootRoutes (เพื่อความสะอาด)
        $this->bootRoutes();

        // ✅ ลงทะเบียน Event Listener ข้าม Module
        Event::listen(
            DeliveryNoteCancelled::class,
            CancelOrderOnDeliveryFailure::class
        );
    }

    /**
     * 👈 3. สร้างฟังก์ชันนี้ขึ้นมาเพื่อจัดการ Route โดยเฉพาะ
     */
    protected function bootRoutes(): void
    {
        // --- สำหรับ Web Routes ---
        $webRoutePath = __DIR__ . '/../../Presentation/Http/routes/sales.php';

        Route::middleware(['web', 'auth', 'verified']) // 👈 นี่คือจุดสำคัญ!
            ->prefix('sales')                   // กำหนด prefix
            ->name('sales.')                      // กำหนด name prefix
            ->group(function () use ($webRoutePath) {
                require $webRoutePath; // โหลดไฟล์ Route ที่เราสร้างไว้
            });

        // --- (Optional) สำหรับ API Routes (ถ้ามี) ---
        $apiRoutePath = __DIR__ . '/../../Presentation/Http/routes/api.php';

        Route::middleware('api') // 👈 ใช้ middleware 'api'
            ->prefix('api/sales')
            ->name('api.sales.')
            ->group(function () use ($apiRoutePath) {
                if (file_exists($apiRoutePath)) {
                    require $apiRoutePath;
                }
            });
    }
}
