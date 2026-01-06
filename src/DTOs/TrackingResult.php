<?php

namespace Quitenoisemaker\ShippingTracker\DTOs;

use Illuminate\Support\Collection;
use DateTime;

class TrackingResult
{
    public function __construct(
        public ?string $trackingNumber,
        public string $status,
        public ?string $provider,
        public ?string $description,
        public ?DateTime $estimatedDelivery,
        public Collection $events,
        public array $raw
    ) {}
}
