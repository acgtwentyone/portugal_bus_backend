<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
echo "http-only: ".count($app->make(Illuminate\Console\Scheduling\Schedule::class)->events())."\n";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "after console bootstrap: ".count($app->make(Illuminate\Console\Scheduling\Schedule::class)->events())."\n";
