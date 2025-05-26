<?php

namespace Quitenoisemaker\ShippingTracker\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Quitenoisemaker\ShippingTracker\Tests\TestCase;
use Quitenoisemaker\ShippingTracker\Events\ShippingWebhookReceived;
use Quitenoisemaker\ShippingTracker\Listeners\HandleShippingWebhook;
use Quitenoisemaker\ShippingTracker\Models\ShippingWebhook;
use Mockery;

class HandleShippingWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../src/database/migrations');

        config([
            'shipping-tracker.cargoplug.base_url' => 'https://test.getcargoplug.com',
            'shipping-tracker.cargoplug.secret_key' => 'secret',
            'shipping-tracker.cargoplug.client_key' => 'client',
            'shipping-tracker.sendbox.base_url' => 'https://test.sendbox.co',
            'shipping-tracker.sendbox.app_id' => 'test-app-id',
            'shipping-tracker.sendbox.client_key' => 'test-client-key',
        ]);
        Http::fake([
            'https://test.getcargoplug.com/user/authenticate' => Http::response(['data' => ['access_token' => 'test-token']], 200),
            'https://test.sendbox.co/oauth/access/test-app-id/refresh?app_id=test-app-id&client_secret=test-client-key' => Http::response(['access_token' => 'new-token'], 200),
        ]);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    // HandleShippingWebhookTest.php
    /** @test */
    public function it_handles_webhook_for_valid_payload()
    {
        $providers = ['sendbox', 'cargoplug'];
        foreach ($providers as $provider) {
            $payload = ['tracking_number' => '101782511', 'status' => 'delivered'];

            Log::shouldReceive('info')
                ->once()
                ->withAnyArgs();
            Log::shouldReceive('error') // Allow error logs
                ->never()
                ->withAnyArgs();

            $webhook = ShippingWebhook::create([
                'provider' => $provider,
                'payload' => $payload,
            ]);

            $listener = new HandleShippingWebhook();
            $listener->handle(new ShippingWebhookReceived($webhook));

            $this->assertDatabaseHas('shipments', [
                'tracking_number' => '101782511',
                'provider' => $provider,
                'status' => 'delivered',
            ]);
        }
    }


    public function webhookProvider(): array
    {
        return [
            'Cargoplug' => ['cargoplug'],
            'Sendbox' => ['sendbox'],
        ];
    }

    /** @test */
    public function it_logs_warning_for_invalid_provider()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Unhandled webhook provider: invalid');

        $webhook = ShippingWebhook::create([
            'provider' => 'invalid',
            'payload' => ['tracking_number' => '101782511'],
        ]);

        $listener = new HandleShippingWebhook();
        $listener->handle(new ShippingWebhookReceived($webhook));

        $this->assertTrue(true);
    }
}
