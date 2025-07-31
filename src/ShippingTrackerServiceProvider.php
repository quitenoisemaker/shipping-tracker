<?php

namespace Quitenoisemaker\ShippingTracker;

use Illuminate\Support\ServiceProvider;

class ShippingTrackerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/shipping-tracker.php',
            'shipping-tracker'
        );

        // Bind the ShippingTracker class as a singleton
        $this->app->singleton(ShippingTracker::class, function ($app) {
            return new ShippingTracker();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish the configuration file
        $this->publishes([
            __DIR__ . '/../config/shipping-tracker.php' => config_path('shipping-tracker.php'),
        ], 'shipping-tracker-config');

        // Load package
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');

        $this->app->register(EventServiceProvider::class);
    }
}
