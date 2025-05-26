<?php

namespace Quitenoisemaker\ShippingTracker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Quitenoisemaker\ShippingTracker\Models\ShippingWebhook;

class ShippingWebhookReceived
{
    use Dispatchable, SerializesModels;

    public ShippingWebhook $webhook;

    public function __construct(ShippingWebhook $webhook)
    {
        $this->webhook = $webhook;
    }
}