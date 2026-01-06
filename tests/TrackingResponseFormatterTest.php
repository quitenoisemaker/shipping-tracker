<?php

namespace Quitenoisemaker\ShippingTracker\Tests;

use Quitenoisemaker\ShippingTracker\Tests\TestCase;
use Quitenoisemaker\ShippingTracker\DTOs\TrackingResult;
use Quitenoisemaker\ShippingTracker\Support\TrackingResponseFormatter;

class TrackingResponseFormatterTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['Quitenoisemaker\ShippingTracker\ShippingTrackerServiceProvider'];
    }

    /** @test */
    public function it_formats_cargoplug_response()
    {
        $data = [
            'tracking_number' => 'CP123',
            'status' => 'in_transit',
            'description' => 'Package',
            'history' => [],
        ];
        $result = TrackingResponseFormatter::formatCargoplug($data);

        $this->assertInstanceOf(TrackingResult::class, $result);
        $this->assertEquals('CP123', $result->trackingNumber);
        $this->assertEquals('in_transit', $result->status);
        $this->assertEquals('Package', $result->description);
    }

    /** @test */
    public function it_throws_exception_for_empty_cargoplug_response()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cargoplug response data is empty');

        TrackingResponseFormatter::formatCargoplug([]);
    }

    /** @test */
    public function it_formats_sendbox_response()
    {
        $data = [
            'code' => 'SB123456',
            'status_code' => 'delivered',
            'delivery_eta' => '2025-05-10',
            'events' => [
                [
                    'date_created' => '2025-05-09T10:00:00Z',
                    'description' => 'Package delivered',
                    'location_description' => 'Lagos, Nigeria',
                    'status' => ['code' => 'delivered'],
                ],
            ],
        ];
        $result = TrackingResponseFormatter::formatSendbox($data);

        $this->assertInstanceOf(TrackingResult::class, $result);
        $this->assertEquals('SB123456', $result->trackingNumber);
        $this->assertEquals('delivered', $result->status);
        // Estimated delivery is now a Carbon object
        $this->assertEquals('2025-05-10', $result->estimatedDelivery->toDateString());
        // Current location is not a direct property anymore, it's derived or just events.
        // Wait, did I remove current_location from TrackingResult?
        // Let's check TrackingResult DTO. It does NOT have current_location.
        // It has description and raw.
        // TrackerResponseFormatter::formatSendbox mapped 'current_location' to... nothing?
        // Ah, in DTO I didn't include `current_location`. I included `location` in `TrackingEvent`.
        // Let me check TrackingResult again.
        
        // TrackingResult:
        // public function __construct(
        //   public string $trackingNumber,
        //   public string $status,
        //   public ?string $provider,
        //   public ?string $description,
        //   public ?DateTime $estimatedDelivery,
        //   public Collection $events,
        //   public array $raw
        // ) {}
        
        // So checking current location: can't do it directly on result.
        // But I can check first event location.
        $this->assertCount(1, $result->events);
        $this->assertEquals('delivered', $result->events[0]->status);
        $this->assertEquals('Lagos, Nigeria', $result->events[0]->location);
    }

    /** @test */
    public function it_throws_exception_for_empty_sendbox_response()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sendbox response data is empty');

        TrackingResponseFormatter::formatSendbox([]);
    }
}
