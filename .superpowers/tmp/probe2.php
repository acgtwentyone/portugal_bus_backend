<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
echo "via ScheduledTaskStatus under HTTP kernel: ".count($app->make(App\Services\ScheduledTaskStatus::class)->events())."\n";
