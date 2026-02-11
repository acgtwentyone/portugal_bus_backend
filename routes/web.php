<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BusLineController;

Route::get('/', function() {
    return view('welcome');
});

Route::domain(config('app.admin_domain'))->group(function () {
    Route::get('/lines', [BusLineController::class, 'index'])->name('admin.lines.index');
    Route::post('/lines/forceSync', [BusLineController::class, 'forceSync'])->name('admin.busLines.forceSync');
});
