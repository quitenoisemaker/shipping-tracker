<?php

namespace Quitenoisemaker\ShippingTracker\Tests;

use Quitenoisemaker\ShippingTracker\Tests\TestCase;
use Quitenoisemaker\ShippingTracker\Support\TrackingResponseFormatter;

class TrackingResponseFormatterTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['Quitenoisemaker\ShippingTracker\ShippingTrackerServiceProvider'];
    }

    /** @test */
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

        $this->assertSame('CP123', $result['tracking_number']);
        $this->assertSame('in_transit', $result['status']);
        $this->assertSame('Package', $result['description']);
    }

    /** @test */
    public function it_throws_exception_for_empty_cargoplug_response()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cargoplug response data is empty');

        TrackingResponseFormatter::formatCargoplug([]);
    }

    /** @test */
    public function it_throws_exception_for_empty_sendbox_response()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sendbox response data is empty');

        TrackingResponseFormatter::formatSendbox([]);
    }
}
