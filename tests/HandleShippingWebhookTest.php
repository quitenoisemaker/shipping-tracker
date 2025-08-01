<?php

namespace Quitenoisemaker\ShippingTracker\Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Quitenoisemaker\ShippingTracker\Tests\TestCase;
use Quitenoisemaker\ShippingTracker\Events\ShippingWebhookReceived;
use Quitenoisemaker\ShippingTracker\Listeners\HandleShippingWebhook;
use Quitenoisemaker\ShippingTracker\Models\ShippingWebhook;
use Quitenoisemaker\ShippingTracker\Providers\CargoplugShippingProvider;
use Mockery;

class HandleShippingWebhookTest extends TestCase
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
            'shipping-tracker.cargoplug.base_url' => 'https://test.getcargoplug.com',
            'shipping-tracker.cargoplug.secret_key' => 'secret',
            'shipping-tracker.cargoplug.client_key' => 'client',
            'shipping-tracker.sendbox.base_url' => 'https://test.sendbox.co',
            'shipping-tracker.sendbox.app_id' => 'test-app-id',
            'shipping-tracker.sendbox.client_key' => 'test-client-key',
        ]);
        Cache::flush(); // Clear cache to prevent interference
        Http::fake([
            'https://test.getcargoplug.com/user/authenticate' => Http::response(['data' => ['access_token' => 'test-token']], 200),
            'https://test.sendbox.co/oauth/access/test-app-id/refresh?app_id=test-app-id&client_secret=test-client-key' => Http::response(['access_token' => 'new-token'], 200),
        ]);
        Http::preventStrayRequests(); // Prevent unmocked requests
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_handles_webhook_for_valid_payload()
    {
        $providers = [
            'sendbox' => [
                'payload' => [
                    'code' => '101782511',
                    'status' => ['code' => 'delivery_started'],
                    'events' => [
                        ['location_description' => 'Lagos Hub', 'timestamp' => '2025-05-30T12:00:00Z', 'status' => ['code' => 'delivery_started']],
                    ],
                    'delivery_eta' => '2025-06-01',
                ],
                'expected' => [
                    'tracking_number' => '101782511',
                    'status' => 'in_transit',
                    'location' => 'Lagos Hub',
                    'estimated_delivery' => '2025-06-01',
                    'history' => json_encode([
                        ['timestamp' => '2025-05-30T12:00:00Z', 'location_description' => 'Lagos Hub', 'status' => 'in_transit'],
                    ]),
                ],
            ],
            'cargoplug' => [
                'payload' => [
                    'tracking_number' => '101782511',
                    'status' => 'delivered',
                    'location' => 'Lagos Hub',
                    'expected_delivery_date' => '2025-06-01',
                    'history' => [
                        ['order_updated' => '2025-05-30T12:00:00Z', 'status' => 'initiated'],
                    ],
                ],
                'expected' => [
                    'tracking_number' => '101782511',
                    'status' => 'delivered',
                    'location' => 'Lagos Hub',
                    'estimated_delivery' => '2025-06-01',
                    'history' => json_encode([
                        ['timestamp' => '2025-05-30T12:00:00Z', 'status' => 'pending'],
                    ]),
                ],
            ],
        ];

        foreach ($providers as $provider => $data) {
            Log::shouldReceive('info')
                ->once()
                ->withAnyArgs();
            Log::shouldReceive('error')
                ->never()
                ->withAnyArgs();
            Log::shouldReceive('warning')
                ->never()
                ->withAnyArgs();

            $webhook = ShippingWebhook::create([
                'provider' => $provider,
                'payload' => $data['payload'],
            ]);

            $listener = new HandleShippingWebhook();
            $listener->handle(new ShippingWebhookReceived($webhook));

            $this->assertDatabaseHas('shipments', array_merge([
                'provider' => $provider,
            ], $data['expected']));
        }
    }

    /** @test */
    public function it_handles_cargoplug_webhook_with_uppercase_and_spaced_status()
    {
        $payload = [
            'tracking_number' => '101782511',
            'status' => 'In Transit',
            'location' => 'Lagos Hub',
            'expected_delivery_date' => '2025-06-01',
            'history' => [
                ['order_updated' => '2025-05-30T12:00:00Z', 'status' => 'PAID'],
            ],
        ];

        Log::shouldReceive('info')
            ->once()
            ->withAnyArgs();
        Log::shouldReceive('error')
            ->never()
            ->withAnyArgs();
        Log::shouldReceive('warning')
            ->never()
            ->withAnyArgs();

        $webhook = ShippingWebhook::create([
            'provider' => 'cargoplug',
            'payload' => $payload,
        ]);

        $listener = new HandleShippingWebhook();
        $listener->handle(new ShippingWebhookReceived($webhook));

        $this->assertDatabaseHas('shipments', [
            'provider' => 'cargoplug',
            'tracking_number' => '101782511',
            'status' => 'in_transit',
            'location' => 'Lagos Hub',
            'estimated_delivery' => '2025-06-01',
            'history' => json_encode([
                ['timestamp' => '2025-05-30T12:00:00Z', 'status' => 'paid'],
            ]),
        ]);
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

    /** @test */
    public function it_handles_cargoplug_track_with_uppercase_and_spaced_status()
    {
        $mockResponse = [
            'data' => [
                [
                    'result' => [
                        'tracking_number' => '101782511',
                        'status' => 'In Transit',
                        'expected_delivery_date' => '2025-06-01',
                        'history' => [
                            ['order_updated' => '2025-05-30T12:00:00Z', 'status' => 'PAID'],
                        ],
                    ],
                ],
            ],
        ];

        // Mock the correct tracking endpoint
        Http::fake([
            'https://test.getcargoplug.com/shipment/tracking/external*' => Http::response($mockResponse, 200),
        ]);

        $provider = new CargoplugShippingProvider();
        $result = $provider->track('101782511');

        $this->assertEquals('in_transit', $result['status']);
        $this->assertEquals('paid', $result['events'][0]['status']);
    }
}