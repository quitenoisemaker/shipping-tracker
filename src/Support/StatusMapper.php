<?php

namespace Quitenoisemaker\ShippingTracker\Support;

class StatusMapper
{
    protected static $map = [
        'sendbox' => [
            'pending' => 'pending',
            'delivery_started' => 'delivery_started',
            'in_transit' => 'in_transit',
            'delivered' => 'delivered',
            'pickup_started' => 'pickup_started',
            'pickup_completed'=> 'pickup_completed'
        ],
        'cargoplug' => [
            'awaiting_pickup' => 'pending',
            'on_the_way' => 'in_transit',
            'completed' => 'delivered',
            'cancelled' => 'failed',
            'Paid' => 'Paid',
            'Disapproved' => 'Disapproved',
        ],
    ];

    public static function normalize(string $provider, string $status): string
    {
        return static::$map[strtolower($provider)][$status] ?? 'unknown';
    }
}