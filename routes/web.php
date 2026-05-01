<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1. Porto:Bus Landing Page & Legal Routes
Route::domain(config('app.landing_domain'))->middleware('set.locale')->group(function () {
    Route::get('/', [LandingController::class, 'index'])->name('landing');
    Route::get('/privacy', [LandingController::class, 'privacy'])->name('legal.privacy');
    Route::get('/terms', [LandingController::class, 'terms'])->name('legal.terms');
    Route::get('/privacy_policy', [LandingController::class, 'privacyPolicy'])->name('legal.privacy_policy');
});

// 2. Admin & Management Routes (Coming Soon)
Route::domain(config('app.admin_domain'))->group(function () {
    Route::get('/admin-placeholder', function () {
        return "Admin Panel - Coming Soon";
    });
});
