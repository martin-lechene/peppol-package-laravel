<?php

namespace PeppolPackage\EInvoices;

use Illuminate\Support\ServiceProvider;

class InvoiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/e-invoices.php', 'e-invoices');

        $this->app->singleton('e-invoices', function ($app) {
            return new InvoiceManager($app['config']->get('e-invoices', []));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/e-invoices.php' => config_path('e-invoices.php'),
        ], 'e-invoices-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
