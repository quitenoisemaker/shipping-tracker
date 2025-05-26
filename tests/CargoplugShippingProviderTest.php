<?php

namespace Quitenoisemaker\ShippingTracker\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Quitenoisemaker\ShippingTracker\Tests\TestCase;
use Quitenoisemaker\ShippingTracker\Providers\CargoplugShippingProvider;
use Quitenoisemaker\ShippingTracker\Exceptions\ShippingException;

class CargoplugShippingProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['Quitenoisemaker\ShippingTracker\ShippingTrackerServiceProvider'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'shipping-tracker.cargoplug.base_url' => 'https://test.getcargoplug.com',
            'shipping-tracker.cargoplug.secret_key' => 'secret',
            'shipping-tracker.cargoplug.client_key' => 'client',
        ]);

        // Prevent real HTTP requests
        Http::preventStrayRequests();

        // Mock authentication request
        Http::fake([
            'https://test.getcargoplug.com/user/authenticate' => Http::response([
                'message' => 'success',
                'status' => 200,
                'data' => ['token_type' => 'Bearer', 'access_token' => 'test-token']
            ], 200),
        ]);

        // Allow Log::debug calls
        Log::shouldReceive('debug')->never();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_tracks_shipment_successfully()
    {
        Http::fake([
            'https://test.getcargoplug.com/user/authenticate' => Http::response([
                'message' => 'success',
                'status' => 200,
                'data' => ['token_type' => 'Bearer', 'access_token' => 'test-token']
            ], 200),
            'https://test.getcargoplug.com/shipment/tracking/external' => Http::response([
                'data' => [
                    [
                        'result' => [
                            'tracking_number' => '101782511',
                            'status' => 'in_transit',
                            'description' => 'Package',
                            'events' => [],
                        ]
                    ]
                ]
            ], 200),
        ]);

        $provider = app(CargoplugShippingProvider::class);
        $result = $provider->track('101782511');

        $this->assertSame('101782511', $result['tracking_number']);
        $this->assertSame('in_transit', $result['status']);
        $this->assertSame('Package', $result['description']);
        $this->assertSame([], $result['events']);
    }

    /** @test */
    public function it_throws_exception_for_failed_tracking()
    {
        Http::fake([
            'https://test.getcargoplug.com/user/authenticate' => Http::response([
                'message' => 'success',
                'status' => 200,
                'data' => ['token_type' => 'Bearer', 'access_token' => 'test-token']
            ], 200),
            'https://test.getcargoplug.com/shipment/tracking/external' => Http::response([
                'message' => 'Invalid tracking number',
                'data' => []
            ], 400),
        ]);

        Log::shouldReceive('error')->once()->withAnyArgs(); // Allow error log

        $this->expectException(ShippingException::class);
        $this->expectExceptionMessage('Cargoplug tracking failed: Invalid tracking number');

        $provider = app(CargoplugShippingProvider::class);
        $provider->track('INVALID123');
    }
}
