<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use TmrEcosystem\Customers\Presentation\Http\Controllers\CustomerController;
use TmrEcosystem\Inventory\Presentation\Http\Controllers\ItemController;
use TmrEcosystem\Logistics\Presentation\Http\Controllers\PublicTrackingController;

// Public Route (ไม่ต้อง Login)
Route::get('/track/{token}', [PublicTrackingController::class, 'show'])->name('public.track');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('customers', CustomerController::class);
});

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/profile/logout-other-sessions', [ProfileController::class, 'logoutOtherBrowserSessions'])->name('profile.logout-other-sessions');
});

require __DIR__ . '/auth.php';
