<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BusLineApiController;

Route::domain(config('app.api_domain'))->group(function () {
    Route::middleware(['auth.bus'])->group(function() {
        Route::prefix('v1')->group(function() {
            Route::get('/lines', [BusLineApiController::class, 'index']);
        });
    });
});
