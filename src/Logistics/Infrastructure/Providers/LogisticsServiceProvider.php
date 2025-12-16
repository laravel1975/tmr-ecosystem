<?php

namespace TmrEcosystem\Logistics\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use TmrEcosystem\Logistics\Application\Listeners\AllocateBackorders;
use TmrEcosystem\Logistics\Application\Listeners\CancelLogisticsDocuments;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Sales\Domain\Events\OrderUpdated; // ✅ Import Event
use TmrEcosystem\Logistics\Application\Listeners\CreateLogisticsDocuments;
use TmrEcosystem\Logistics\Application\Listeners\SyncDeliveryNoteFromPicking;
use TmrEcosystem\Logistics\Application\Listeners\SyncLogisticsDocuments; // ✅ Import Listener
use TmrEcosystem\Logistics\Domain\Events\PickingSlipUpdated;
use TmrEcosystem\Sales\Domain\Events\OrderCancelled;
use TmrEcosystem\Stock\Domain\Events\StockReceived;

class LogisticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {

        // โหลด Routes โดยกำหนด Prefix และ Middleware
        $this->bootRoutes();

        // 1. Sales -> Logistics Sync
        Event::listen(
            OrderConfirmed::class,
            [CreateLogisticsDocuments::class, 'handle']
        );
        
        Event::listen(OrderUpdated::class, SyncLogisticsDocuments::class); // นี่คือตัวหลักที่แก้
        Event::listen(OrderCancelled::class, CancelLogisticsDocuments::class);

        // 2. Stock -> Logistics Allocation
        Event::listen(StockReceived::class, AllocateBackorders::class);

        // 3. ✅ Logistics Internal Sync (Picking -> Delivery Note)
        Event::listen(PickingSlipUpdated::class, SyncDeliveryNoteFromPicking::class);

        // โหลด Migrations (ถ้ามีเฉพาะของ module นี้)
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    protected function bootRoutes(): void
    {
        Route::middleware(['web', 'auth', 'verified'])
            ->prefix('logistics')
            ->name('logistics.')
            ->group(__DIR__ . '/../../Presentation/Http/Routes/logistics.php');
    }
}
