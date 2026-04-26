<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BusLineController;


use App\Http\Controllers\LandingController;

Route::domain(config('app.admin_domain'))->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });
});

Route::domain(config('app.landing_domain'))->group(function () {
    Route::get('/privacy', [LandingController::class, 'privacy'])->name('legal.privacy');
    Route::get('/terms', [LandingController::class, 'terms'])->name('legal.terms');
    Route::get('/privacy_policy', [LandingController::class, 'privacyPolicy'])->name('legal.privacy_policy');
});
