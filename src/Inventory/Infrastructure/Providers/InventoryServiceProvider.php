<?php

namespace TmrEcosystem\Inventory\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Inventory\Application\Services\ItemLookupService;

/**
 * --- 1. Import Domain Repository Interfaces ---
 * (นี่คือ "สัญญาท" ที่เรากำหนดไว้ใน Domain Layer ว่าต้องทำอะไรได้บ้าง)
 */

use TmrEcosystem\Inventory\Domain\Repositories\ItemRepositoryInterface;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Repositories\EloquentItemRepository;

class InventoryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // เมื่อ Controller หรือ UseCase ร้องขอ ItemRepositoryInterface
        // ให้ส่ง EloquentItemRepository กลับไป
        $this->app->bind(
            ItemRepositoryInterface::class,
            EloquentItemRepository::class
        );

        // ✅ เพิ่ม Binding ใหม่: ใครขอ Interface นี้ ให้ส่ง Service ตัวจริงไป
        $this->app->bind(
            ItemLookupServiceInterface::class,
            ItemLookupService::class
        );

        $this->app->bind(
            \TmrEcosystem\Inventory\Domain\Repositories\StockReservationRepositoryInterface::class,
            \TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Repositories\EloquentStockReservationRepository::class
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
    }

    /**
     * 👈 3. สร้างฟังก์ชันนี้ขึ้นมาเพื่อจัดการ Route โดยเฉพาะ
     */
    protected function bootRoutes(): void
    {
        // --- สำหรับ Web Routes ---
        $webRoutePath = __DIR__ . '/../../Presentation/Http/routes/inventory.php';

        Route::middleware(['web', 'auth', 'verified']) // 👈 นี่คือจุดสำคัญ!
            ->prefix('inventory')                   // กำหนด prefix
            ->name('inventory.')                      // กำหนด name prefix
            ->group(function () use ($webRoutePath) {
                require $webRoutePath; // โหลดไฟล์ Route ที่เราสร้างไว้
            });

        // --- (Optional) สำหรับ API Routes (ถ้ามี) ---
        $apiRoutePath = __DIR__ . '/../../Presentation/Http/routes/api.php';

        Route::middleware('api') // 👈 ใช้ middleware 'api'
            ->prefix('api/inventory')
            ->name('api.inventory.')
            ->group(function () use ($apiRoutePath) {
                if (file_exists($apiRoutePath)) {
                    require $apiRoutePath;
                }
            });
    }
}
