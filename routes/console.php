<?php

use App\Console\Commands\ProcessArrivalAlerts;
use App\Console\Commands\SyncStcpLines;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Runs every day at 03h dawn
Schedule::command(SyncStcpLines::class)
    ->weekends()
    ->at('03:00')
    ->timezone('Europe/Lisbon')
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::error('Sync STCP falhou (sábado/domingo 03:00).');
    })
    ->onSuccess(function () {
        Log::info('Sync STCP concluído com sucesso.');
    });

// Polls active arrival alerts every minute and pushes via FCM when the bus is close
Schedule::command(ProcessArrivalAlerts::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::error('Processamento de alertas de chegada falhou.');
    });