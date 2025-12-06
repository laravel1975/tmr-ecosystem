<?php

use Illuminate\Support\Facades\Route;
use TmrEcosystem\Purchase\Presentation\Http\Controllers\PurchaseOrderController;
use TmrEcosystem\Purchase\Presentation\Http\Controllers\VendorController;

Route::middleware(['auth', 'verified'])->prefix('purchase')->name('purchase.')->group(function () {

    // Purchase Orders Routes (Existing)
    Route::resource('orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('orders/{order}/confirm', [PurchaseOrderController::class, 'confirm'])->name('orders.confirm');

    // Vendors Routes (New)
    Route::resource('vendors', VendorController::class);
});
