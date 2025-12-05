<?php

namespace TmrEcosystem\Purchase\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class PurchaseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        Route::middleware('web')
            ->group(__DIR__ . '/../../Presentation/Http/routes/purchase.php');
    }
}
