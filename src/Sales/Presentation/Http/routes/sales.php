<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use TmrEcosystem\Sales\Presentation\Http\Controllers\OrderController;
use TmrEcosystem\Sales\Presentation\Http\Controllers\OrderPdfController;
use TmrEcosystem\Sales\Presentation\Http\Controllers\SalesApprovalController;

/*
|--------------------------------------------------------------------------
| Sales Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/orders', [OrderController::class, 'index'])->name('index');
Route::get('/dashboard', [OrderController::class, 'dashboard'])->name('dashboard');
Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
Route::put('/orders/{id}', [OrderController::class, 'update'])->name('orders.update');
Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->name('orders.destroy');

Route::get('/orders/{id}/pdf', [OrderPdfController::class, 'download'])->name('orders.pdf');

Route::get('/approvals', [SalesApprovalController::class, 'index'])->name('approvals.index');
