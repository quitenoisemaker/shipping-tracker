<?php

namespace Quitenoisemaker\ShippingTracker\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Quitenoisemaker\ShippingTracker\Tests\TestCase;
use Quitenoisemaker\ShippingTracker\Events\ShippingWebhookReceived;

class WebhookControllerTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['Quitenoisemaker\ShippingTracker\ShippingTrackerServiceProvider'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../src/database/migrations');
        config([
            'shipping-tracker.providers' => [
                'cargoplug' => \Quitenoisemaker\ShippingTracker\Providers\CargoplugShippingProvider::class,
                'sendbox' => \Quitenoisemaker\ShippingTracker\Providers\SendboxShippingProvider::class,
            ],
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
        Event::fake();
    }

    /**
     * @test
     * @dataProvider webhookProvider
     */
    public function it_handles_webhook_and_stores_it(string $provider)
    {
        if ($provider === 'cargoplug') {
            $payload = ['tracking_number' => '101782511', 'status' => 'delivered'];
        } elseif ($provider === 'sendbox') {
            $payload = ['events' => [], 'status' => 'delivered'];
        }

        $response = $this->postJson("/api/shipping/webhooks/{$provider}", $payload);
        $response->assertStatus(200)->assertJson(['message' => 'Webhook received']);

        $this->assertDatabaseHas('shipping_webhooks', [
            'provider' => $provider,
            'payload' => json_encode($payload),
        ]);

        Event::assertDispatched(ShippingWebhookReceived::class, function ($event) use ($provider, $payload) {
            return $event->webhook->provider === $provider && $event->webhook->payload === $payload;
        });
    }

    public static function webhookProvider(): array
    {
        return [
            'Cargoplug' => ['cargoplug'],
            'Sendbox' => ['sendbox'],
        ];
    }

    /** @test */
    public function it_throws_exception_for_invalid_provider()
    {
        $response = $this->postJson('/api/shipping/webhooks/invalid', ['tracking_number' => '101782511']);
        $response->assertStatus(400)->assertJson(['message' => 'Webhook processing failed for invalid: Invalid provider: invalid']);
    }
}
