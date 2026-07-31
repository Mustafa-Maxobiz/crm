<?php

namespace Webkul\SmrtPhone\Providers;

use Illuminate\Support\ServiceProvider;

class SmrtPhoneServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->app->register(ModuleServiceProvider::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/smrtphone.php', 'smrtphone');
    }
}
