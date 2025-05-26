<?php

namespace Quitenoisemaker\ShippingTracker\Tests;

use Quitenoisemaker\ShippingTracker\Tests\TestCase;
use Quitenoisemaker\ShippingTracker\Models\ShippingWebhook;

class ShippingWebhookTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['Quitenoisemaker\ShippingTracker\ShippingTrackerServiceProvider'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../src/database/migrations');
    }

    /** @test */
    public function it_stores_webhook_with_correct_payload()
    {
        $payload = ['tracking_number' => '101782511', 'status' => 'delivered'];

        $webhook = ShippingWebhook::create([
            'provider' => 'cargoplug',
            'payload' => $payload,
        ]);

        $this->assertDatabaseHas('shipping_webhooks', [
            'provider' => 'cargoplug',
            'payload' => json_encode($payload),
        ]);

        $this->assertSame($payload, $webhook->payload);
    }

    /** @test */
    public function it_casts_payload_to_array()
    {
        $payload = ['tracking_number' => '101782511', 'status' => 'delivered'];

        $webhook = ShippingWebhook::create([
            'provider' => 'sendbox',
            'payload' => $payload,
        ]);

        $this->assertIsArray($webhook->payload);
        $this->assertSame($payload, $webhook->payload);
    }
}