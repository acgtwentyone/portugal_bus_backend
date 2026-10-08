<?php

namespace App\Providers;

use App\Listeners\RecordScheduledTaskRun;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Messaging::class, function () {
            return (new Factory)
                ->withServiceAccount(config('services.firebase.credentials'))
                ->createMessaging();
        });

        // Auto-discovered for the scheduler events; must be a singleton so a run's Starting and
        // Finished events land on the same instance (it maps task objects to run rows).
        $this->app->singleton(RecordScheduledTaskRun::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
