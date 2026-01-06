<?php

namespace Quitenoisemaker\ShippingTracker\DTOs;

use DateTime;

class TrackingEvent
{
    public function __construct(
        public string $status,
        public ?string $description,
        public ?string $location,
        public ?DateTime $timestamp
    ) {}
}
