<?php

namespace Quitenoisemaker\ShippingTracker\Tests;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Quitenoisemaker\ShippingTracker\Tests\TestCase;
use Quitenoisemaker\ShippingTracker\Models\Shipment;
use Quitenoisemaker\ShippingTracker\ShippingTracker;
use Quitenoisemaker\ShippingTracker\Exceptions\ShippingException;
use Quitenoisemaker\ShippingTracker\Providers\SendboxShippingProvider;
use Quitenoisemaker\ShippingTracker\Providers\CargoplugShippingProvider;
use Mockery;

class ShippingTrackerTest extends TestCase
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
                'cargoplug' => CargoplugShippingProvider::class,
                'sendbox' => SendboxShippingProvider::class,
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
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_uses_default_provider()
    {
        config(['shipping-tracker.default' => 'cargoplug']);
        $tracker = app(ShippingTracker::class);
        $this->assertInstanceOf(CargoplugShippingProvider::class, $tracker->getProvider());
    }

    /** @test */
    public function it_switches_provider_with_use_method()
    {
        $tracker = app(ShippingTracker::class)->use('sendbox');
        $this->assertInstanceOf(SendboxShippingProvider::class, $tracker->getProvider());

        $tracker->use('cargoplug');
        $this->assertInstanceOf(CargoplugShippingProvider::class, $tracker->getProvider());
    }

    /** @test */
    public function it_throws_exception_for_invalid_provider()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Shipping provider [invalid] not configured.');
        app(ShippingTracker::class)->use('invalid');
    }

    /** @test */
    public function it_throws_exception_when_no_provider_supports_tracking_number()
    {
        $providers = config('shipping-tracker.providers', []);
        $providerKeys = array_keys($providers);

        // Override all providers to fail
        foreach ($providerKeys as $key) {
            config([
                "shipping-tracker.$key.base_url" => null,
            ]);
        }

        // Allow Log::debug() calls
        Log::shouldReceive('debug')->never();

        $this->expectException(ShippingException::class);
        $this->expectExceptionMessageMatches("/Failed to initialize provider/");

        $tracker = app(ShippingTracker::class);
        $tracker->track('INVALID123');
    }

    /** @test */
    public function it_tracks_multiple_shipments()
    {
        Http::fake([
            'https://test.getcargoplug.com/shipment/tracking/external' => Http::response([
                'data' => [
                    [
                        'result' => [
                            'tracking_number' => 'CP123',
                            'status' => 'in_transit',
                            'history' => [],
                        ]
                    ]
                ]
            ], 200),
            'https://test.sendbox.co/shipping/tracking' => Http::response([
                'status' => ['code' => 'delivered'],
                'events' => [],
                'delivery_eta' => '2025-05-05',
            ], 200),
        ]);

        $tracker = app(ShippingTracker::class);
        $results = [];
        $results['CP123'] = $tracker->use('cargoplug')->track('CP123');
        $results['SB456'] = $tracker->use('sendbox')->track('SB456');

        $this->assertSame('in_transit', $results['CP123']['status']);
        $this->assertSame('delivered', $results['SB456']['raw']['status']['code']);
    }

    // ShippingTrackerTest.php
    /** @test */
    public function it_retrieves_shipment_history()
    {
        Shipment::create([
            'tracking_number' => 'SB123',
            'provider' => 'sendbox',
            'status' => 'delivered',
            'history' => [['description' => 'Delivered']],
        ]);

        $tracker = app(ShippingTracker::class);
        $history = $tracker->getHistory('SB123');

        $this->assertSame('SB123', $history->tracking_number);
        $this->assertSame('delivered', $history->status);
        $this->assertCount(1, $history->history);
    }

    // ShippingTrackerTest.php
    /** @test */
    public function it_uses_cached_provider_for_known_tracking_number()
    {
        Cache::put('shipping_tracker_provider_SB123', 'sendbox', now()->addDays(7));

        Http::fake([
            'https://test.sendbox.co/shipping/tracking' => Http::response([
                'status' => ['code' => 'delivered'],
                'events' => [],
                'delivery_eta' => '2025-05-05',
            ], 200),
        ]);

        $tracker = app(ShippingTracker::class);
        $result = $tracker->track('SB123');

        $this->assertSame('delivered', $result['raw']['status']['code']);
        $this->assertInstanceOf(SendboxShippingProvider::class, $tracker->getProvider());
    }
}
