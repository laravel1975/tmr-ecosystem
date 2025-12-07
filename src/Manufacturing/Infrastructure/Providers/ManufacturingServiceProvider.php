<?php

namespace TmrEcosystem\Manufacturing\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class ManufacturingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerMigrations();
    }

    public function register(): void
    {
        // พื้นที่สำหรับ Bind Interface กับ Repository ในอนาคต
        // $this->app->bind(BomRepositoryInterface::class, EloquentBomRepository::class);
    }

    protected function registerRoutes(): void
    {
        // โหลด Route ที่เราสร้างตะกี้
        $this->loadRoutesFrom(__DIR__ . '/../../Presentation/Http/routes/manufacturing.php');
    }

    protected function registerMigrations(): void
    {
        // บอก Laravel ว่า Migration ของ Module นี้อยู่ที่ไหน
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }
}
