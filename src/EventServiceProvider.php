<?php

namespace Quitenoisemaker\ShippingTracker;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Quitenoisemaker\ShippingTracker\Events\ShippingWebhookReceived;
use Quitenoisemaker\ShippingTracker\Listeners\HandleShippingWebhook;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ShippingWebhookReceived::class => [
            HandleShippingWebhook::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}