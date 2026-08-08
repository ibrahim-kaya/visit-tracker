<?php

namespace IbrahimKaya\VisitTracker;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use IbrahimKaya\VisitTracker\Listeners\AttributeVisitsToUser;
use IbrahimKaya\VisitTracker\Middleware\VisitTracker;

class VisitTrackerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/visit-tracker.php', 'visit-tracker');
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/visit-tracker.php' => config_path('visit-tracker.php'),
        ], 'visit-tracker-config');

        $this->registerAuthAttributionListeners();

        // Laravel 11+ rebuilds the web group after provider boot(); register late
        // so VisitTracker is not wiped by the framework middleware configuration.
        $this->app->booted(function () {
            $this->registerWebMiddleware();
        });

        $this->app->afterResolving(HttpKernel::class, function () {
            $this->registerWebMiddleware();
        });
    }

    protected function registerAuthAttributionListeners(): void
    {
        Event::listen(Login::class, AttributeVisitsToUser::class);
        Event::listen(Registered::class, AttributeVisitsToUser::class);
    }

    protected function registerWebMiddleware(): void
    {
        $router = $this->app['router'];
        $group = $router->getMiddlewareGroups()['web'] ?? [];

        if (! in_array(VisitTracker::class, $group, true)) {
            $router->pushMiddlewareToGroup('web', VisitTracker::class);
        }
    }
}
