<?php

namespace Webkul\Lead\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Webkul\Lead\Console\Commands\SendFollowupReminders;

class LeadServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(Router $router)
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'lead');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SendFollowupReminders::class,
            ]);
        }
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register() {}
}
