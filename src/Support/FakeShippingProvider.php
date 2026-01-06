<?php

namespace Quitenoisemaker\ShippingTracker\Support;

use Quitenoisemaker\ShippingTracker\Contracts\ShippingProviderInterface;
use Quitenoisemaker\ShippingTracker\DTOs\TrackingResult;
use Quitenoisemaker\ShippingTracker\DTOs\TrackingEvent;
use Illuminate\Support\Collection;

class FakeShippingProvider implements ShippingProviderInterface
{
    public function track(string $trackingNumber): TrackingResult
    {
        return new TrackingResult(
            trackingNumber: $trackingNumber,
            status: 'in_transit',
            provider: 'fake',
            description: 'Fake shipment description',
            estimatedDelivery: now()->addDays(2),
            events: new Collection([
                new TrackingEvent('picked_up', 'Package received', 'Lagos', now()->subDay()),
                new TrackingEvent('in_transit', 'Package in transit', 'Ibadan', now())
            ]),
            raw: ['foo' => 'bar']
        );
    }

    public function handleWebhook(array $payload): void
    {
        // Do nothing
    }

    public function checkHealth(): bool
    {
        return true;
    }

    public function supports(string $trackingNumber): bool
    {
        return true;
    }
}
