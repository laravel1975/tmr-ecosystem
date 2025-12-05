<?php

use Illuminate\Support\Facades\Route;
use TmrEcosystem\Purchase\Presentation\Http\Controllers\PurchaseOrderController;

Route::middleware(['auth', 'verified'])->prefix('purchase')->name('purchase.')->group(function () {
    Route::resource('orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show']);
});
