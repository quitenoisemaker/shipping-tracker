<?php

namespace Quitenoisemaker\ShippingTracker\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Quitenoisemaker\ShippingTracker\Tests\TestCase;
use Quitenoisemaker\ShippingTracker\Providers\SendboxShippingProvider;
use Quitenoisemaker\ShippingTracker\Exceptions\ShippingException;
use Quitenoisemaker\ShippingTracker\DTOs\TrackingResult;

class SendboxShippingProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['Quitenoisemaker\ShippingTracker\ShippingTrackerServiceProvider'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'shipping-tracker.sendbox.base_url' => 'https://test.sendbox.co',
            'shipping-tracker.sendbox.app_id' => 'test-app-id',
            'shipping-tracker.sendbox.client_key' => 'test-client-key',
        ]);
    }

    /** @test */
    public function it_throws_exception_for_missing_config()
    {
        config([
            'shipping-tracker.sendbox.base_url' => null,
            'shipping-tracker.sendbox.app_id' => null,
            'shipping-tracker.sendbox.client_key' => null,
        ]);

        $this->expectException(ShippingException::class);
        $this->expectExceptionMessage('Sendbox base URL is not configured.');

        new SendboxShippingProvider();
    }

    /** @test */
    public function it_uses_static_access_token_if_configured()
    {
        config(['shipping-tracker.sendbox.access_token' => 'static-token']);

        // Mock to ensure NO refresh request is made. If it is made, Http::assertNothingSent will fail.
        Http::fake();

        $provider = new SendboxShippingProvider();
        
        $this->assertSame('static-token', $provider->getToken());
        Http::assertNothingSent();
    }

    /** @test */
    public function it_refreshes_access_token()
    {
        Http::fake([
            'https://test.sendbox.co/oauth/access/test-app-id/refresh?app_id=test-app-id&client_secret=test-client-key' => Http::response(['access_token' => 'new-token'], 200),
        ]);

        $provider = new SendboxShippingProvider();
        $this->assertSame('new-token', $provider->getToken());
        $this->assertSame('new-token', Cache::get('sendbox_access_token'));
    }

    /** @test */
    public function it_throws_exception_for_failed_token_refresh()
    {
        Http::fake([
            'https://test.sendbox.co/oauth/access/test-app-id/refresh?app_id=test-app-id&client_secret=test-client-key' => Http::response(['message' => 'Invalid credentials'], 401),
        ]);

        $this->expectException(ShippingException::class);
        $this->expectExceptionMessage('Failed to refresh Sendbox access token: Invalid credentials');

        new SendboxShippingProvider();
    }



    /** @test */
    public function it_tracks_shipment_successfully()
    {
        Http::fake([
            'https://test.sendbox.co/oauth/access/test-app-id/refresh?app_id=test-app-id&client_secret=test-client-key' => Http::response(['access_token' => 'new-token'], 200),
            'https://test.sendbox.co/shipping/tracking' => Http::response([
                'status' => ['code' => 'delivered'],
                'events' => [],
                'delivery_eta' => '2025-05-05',
                'code' => '101782511',
            ], 200),
        ]);

        $provider = new SendboxShippingProvider();
        $result = $provider->track('101782511');

        $this->assertInstanceOf(TrackingResult::class, $result);
        $this->assertEquals('delivered', $result->raw['status']['code'] ?? null);
        $this->assertEquals('101782511', $result->trackingNumber);
    }

    /** @test */
    public function it_throws_exception_for_invalid_tracking_number()
    {
        Http::fake([
            'https://test.sendbox.co/oauth/access/test-app-id/refresh?app_id=test-app-id&client_secret=test-client-key' => Http::response(['access_token' => 'new-token'], 200),
            'https://test.sendbox.co/shipping/tracking' => Http::response(['message' => 'Invalid tracking number'], 400),
        ]);

        $this->expectException(ShippingException::class);
        $this->expectExceptionMessage('Sendbox tracking failed: Invalid tracking number');

        $provider = new SendboxShippingProvider();
        $provider->track('INVALID123');
    }

    /** @test */
    public function it_logs_warning_for_invalid_webhook_payload()
    {
        Http::fake([
            'https://test.sendbox.co/oauth/access/test-app-id/refresh?app_id=test-app-id&client_secret=test-client-key' => Http::response(['access_token' => 'new-token'], 200),
        ]);

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->with('Invalid Sendbox webhook payload', \Mockery::any());

        $provider = new SendboxShippingProvider();
        
        // Missing 'events' field - should log warning
        $provider->handleWebhook([
            'status' => ['code' => 'delivered'],
        ]);
    }

    /** @test */
    public function it_processes_valid_webhook_payload()
    {
        Http::fake([
            'https://test.sendbox.co/oauth/access/test-app-id/refresh?app_id=test-app-id&client_secret=test-client-key' => Http::response(['access_token' => 'new-token'], 200),
        ]);

        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->with('Sendbox webhook processed', \Mockery::any());

        $provider = new SendboxShippingProvider();
        
        // Valid payload with both 'events' and 'status' fields
        $provider->handleWebhook([
            'events' => [['location_description' => 'Lagos Hub']],
            'status' => ['code' => 'delivered'],
        ]);
    }
}
