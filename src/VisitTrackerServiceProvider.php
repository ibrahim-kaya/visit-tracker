<?php

namespace IbrahimKaya\VisitTracker;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use IbrahimKaya\VisitTracker\Middleware\VisitTracker;

class VisitTrackerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Config publish
        $this->publishes([
            __DIR__ . '/../config/visit-tracker.php' => config_path('visit-tracker.php'),
        ], 'visit-tracker-config');

        // Config merge
        $this->mergeConfigFrom(__DIR__ . '/../config/visit-tracker.php', 'visit-tracker');

        // Middleware for all web routes
        $kernel = $this->app->make(HttpKernel::class);
        $kernel->pushMiddleware(VisitTracker::class);
    }

    public function register()
    {
        //
    }
}
