<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

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

// 2. Admin panel (behind Cloudflare Access)
Route::domain(config('app.admin_domain'))->middleware('admin.access')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/actions', [AuditLogController::class, 'index'])->name('actions.index');
});
