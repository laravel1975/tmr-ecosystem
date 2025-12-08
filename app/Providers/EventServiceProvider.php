<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;
use App\Listeners\UserActivityListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

// Domain Events
use TmrEcosystem\HRM\Domain\Events\EmployeeRateUpdated;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Stock\Domain\Events\StockLevelUpdated;

// Listeners
use TmrEcosystem\Logistics\Application\Listeners\CreateLogisticsDocuments;
use TmrEcosystem\Maintenance\Application\Listeners\SyncStockToLegacySparePart;
use TmrEcosystem\Maintenance\Application\Listeners\UpdateMaintenanceTechnicianData;
use TmrEcosystem\Manufacturing\Application\Listeners\CreateProductionOrderFromSales;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // ✅ [ย้ายออกมาข้างนอก] Stock Update Event
        StockLevelUpdated::class => [
            SyncStockToLegacySparePart::class,
        ],

        // Auth Events
        Login::class => [
            UserActivityListener::class,
        ],
        Logout::class => [
            UserActivityListener::class,
        ],
        Failed::class => [
            UserActivityListener::class,
        ],

        // -------------------------------------------------------
        // ✅ [Sales -> Logistics -> Manufacturing Flow]
        // -------------------------------------------------------
        OrderConfirmed::class => [
            CreateLogisticsDocuments::class,
            CreateProductionOrderFromSales::class,
        ],

        /**
         * (HRM Bounded Context)
         */
        EmployeeRateUpdated::class => [
            UpdateMaintenanceTechnicianData::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
