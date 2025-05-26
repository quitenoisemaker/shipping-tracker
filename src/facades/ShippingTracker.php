<?php

namespace Quitenoisemaker\ShippingTracker\facades;

use Illuminate\Support\Facades\Facade;

class ShippingTracker extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        // Bind to the actual class, not a string key
        return \Quitenoisemaker\ShippingTracker\ShippingTracker::class;
    }
}
