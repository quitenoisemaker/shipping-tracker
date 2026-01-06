<?php

namespace Quitenoisemaker\ShippingTracker\Tests;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Quitenoisemaker\ShippingTracker\Tests\TestCase;
use Quitenoisemaker\ShippingTracker\Exceptions\ShippingException;
use Quitenoisemaker\ShippingTracker\Providers\DhlShippingProvider;
use Quitenoisemaker\ShippingTracker\DTOs\TrackingResult;

class DhlShippingProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['Quitenoisemaker\ShippingTracker\ShippingTrackerServiceProvider'];
    }

    public function setUp(): void
    {
        parent::setUp();

        config([
            'shipping-tracker.dhl.base_url' => 'https://test.api-eu.dhl.com',
            'shipping-tracker.dhl.api_key' => 'test-api-key',
        ]);

        // Prevent real HTTP requests
        Http::preventStrayRequests();

         Http::fake([
            'https://test.api-eu.dhl.com/track/shipments?trackingNumber=10178251190' => Http::response([
                'shipments' => [
                    [
                        'id' => '10178251190',
                        'status' => [
                            'timestamp' => '2025-05-01T10:00:00Z',
                            'location' => [
                                'address' => [
                                    'addressLocality' => 'Amsterdam',
                                    'countryCode' => 'NL'
                                ]
                            ],
                            'statusCode' => 'delivered',
                            'status' => '101',
                            'description' => 'Delivered',
                        ],
                        'events' => [
                            array(
                                'timestamp' => '2025-05-01T10:00:00Z',
                                'location' => [
                                    'address' => [
                                        'addressLocality' => 'Amsterdam',
                                        'countryCode' => 'NL'
                                    ]
                                ],
                                'statusCode' => 'delivered',
                                'status' => '101',
                                'description' => 'Delivered',
                            ),
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Allow Log::debug calls
        Log::shouldReceive('debug')->never();
        //Log::shouldReceive('error')->never();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }



    /** @test */
    public function it_tracks_shipment_successfully()
    {
        $provider = new DhlShippingProvider();
        $result = $provider->track('10178251190');

        $this->assertInstanceOf(TrackingResult::class, $result);
        $this->assertSame('delivered', $result->status);
        $this->assertSame('10178251190', $result->trackingNumber);
    }

    /** @test */
    public function it_throws_exception_for_failed_tracking()
    {
        Http::fake([
            'https://test.api-eu.dhl.com/track/shipments?trackingNumber=INVALID123' => Http::response([], 500),
        ]);
        
        $this->expectException(ShippingException::class);
        $this->expectExceptionMessage('DHL tracking failed');

        Log::shouldReceive('error')->once();
        
        $provider = app(DhlShippingProvider::class);
        $provider->track('INVALID123');
    }
}
