<?php

namespace TmrEcosystem\Approval\Infrastructure\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use TmrEcosystem\Approval\Application\Listeners\ApprovalCompletedListener;
use TmrEcosystem\Approval\Domain\Events\WorkflowCompleted;
use TmrEcosystem\Approval\Domain\Models\ApprovalRequest;
use TmrEcosystem\Approval\Domain\Services\ConditionChecker;

class ApprovalServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        // Bind Service ที่เป็น Logic หลัก
        $this->app->singleton(
            ConditionChecker::class
        );
    }

    public function boot(): void
    {
        Event::listen(
            WorkflowCompleted::class,
            ApprovalCompletedListener::class
        );

        // 1. Register Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // 2. Register Routes
        $this->registerRoutes();

        // 3. (Optional) Load Views (ถ้ามี UI Blade/Inertia ที่เรียกผ่าน Controller ของ Approval เอง)
        // $this->loadViewsFrom(__DIR__ . '/../../Presentation/Resources/views', 'approval');
    }

    protected function registerRoutes(): void
    {
        // API Routes
        Route::prefix('api/approval')
            ->middleware(['api', 'auth:sanctum'])
            ->group(__DIR__ . '/../../Presentation/Http/routes/api.php');

        // Web Routes (Inertia)
        Route::middleware(['web', 'auth'])
            ->group(__DIR__ . '/../../Presentation/Http/routes/approve.php');
    }
}
