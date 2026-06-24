<?php

namespace Webkul\MetaLead\Providers;

use Illuminate\Support\ServiceProvider;

class MetaLeadServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'meta_lead');

        $this->app->register(ModuleServiceProvider::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/meta_lead.php', 'meta_lead');
    }
}
