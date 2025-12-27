<?php

namespace TmrEcosystem\Customers\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

class CustomerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \TmrEcosystem\Sales\Application\Contracts\CustomerLookupInterface::class,
            \TmrEcosystem\Customers\Infrastructure\Services\SalesCustomerLookupService::class
        );
    }

    public function boot(): void
    {
        // โหลด Migration จากโฟลเดอร์ Database/Migrations ของ Customers
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }
}
