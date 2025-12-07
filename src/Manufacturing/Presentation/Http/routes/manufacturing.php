<?php

use Illuminate\Support\Facades\Route;
use TmrEcosystem\Manufacturing\Presentation\Http\Controllers\BomController;
use TmrEcosystem\Manufacturing\Presentation\Http\Controllers\ManufacturingDashboardController;
use TmrEcosystem\Manufacturing\Presentation\Http\Controllers\ProductionOrderController;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('manufacturing')
    ->name('manufacturing.')
    ->group(function () {

        // ✅ เปลี่ยนจาก Closure Function เป็น Controller
        Route::get('/dashboard', [ManufacturingDashboardController::class, 'index'])->name('dashboard.index');

        Route::get('/boms', [BomController::class, 'index'])->name('boms.index');
        Route::post('/boms', [BomController::class, 'store'])->name('boms.store');
        Route::get('/boms/create', [BomController::class, 'create'])->name('boms.create');

        Route::get('/production-orders', [ProductionOrderController::class, 'index'])->name('production-orders.index');
        Route::get('/production-orders/create', [ProductionOrderController::class, 'create'])->name('production-orders.create');
        Route::post('/production-orders', [ProductionOrderController::class, 'store'])->name('production-orders.store');
    });

// API Routes (ถ้ามี)
Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api/manufacturing')
    ->name('api.manufacturing.')
    ->group(function () {
        // Route::get('/boms/{bom}', [BomController::class, 'apiShow']);
    });
