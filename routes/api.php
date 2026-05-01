<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StcpApiController;

Route::domain(config('app.api_domain'))->group(function () {
    Route::middleware(['auth.bus'])->group(function () {
        Route::prefix('v1')->group(function () {
            Route::get('/lines', [StcpApiController::class, 'lines']);
            Route::get('/lines/{code}/stops', [StcpApiController::class, 'stops']);
        });
    });
});
