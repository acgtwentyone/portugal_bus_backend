<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StcpApiController;
use App\Http\Controllers\Api\UnirApiController;
use App\Http\Controllers\Api\AppEventApiController;
use App\Http\Controllers\Api\DeviceTokenApiController;
use App\Http\Controllers\Api\ArrivalAlertApiController;

Route::domain(config('app.api_domain'))->group(function () {
    Route::middleware(['auth.bus'])->group(function () {
        Route::prefix('v1')->group(function () {
            Route::get('/lines', [StcpApiController::class, 'lines']);
            Route::get('/lines/{code}/stops', [StcpApiController::class, 'stops']);
            Route::get('/stops/{stopId}/schedule-cards', [StcpApiController::class, 'scheduleCards']);

            Route::get('/unir/lines', [UnirApiController::class, 'lines']);
            Route::get('/unir/lines/{code}/stops', [UnirApiController::class, 'stops']);
            Route::get('/unir/stops/{stopCode}/realtime', [UnirApiController::class, 'realtime']);

            Route::post('/events', [AppEventApiController::class, 'store']);
            Route::get('/events/summary', [AppEventApiController::class, 'summary']);

            Route::post('/devices', [DeviceTokenApiController::class, 'store']);

            Route::post('/alerts', [ArrivalAlertApiController::class, 'store']);
            Route::delete('/alerts/{alert}', [ArrivalAlertApiController::class, 'destroy']);
        });
    });
});
