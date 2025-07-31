<?php

namespace Quitenoisemaker\ShippingTracker\Support;

class StatusMapper
{
    protected static $map = [
        'sendbox' => [
            'pending' => 'pending',
            'delivery_started' => 'in_transit',
            'in_transit' => 'in_transit',
            'delivered' => 'delivered',
            'pickup_started' => 'in_transit',
            'pickup_completed' => 'in_transit',
            'cancelled' => 'cancelled',
        ],
        'cargoplug' => [
            'received_abroad' => 'in_transit',
            'pending_initiation' => 'pending',
            'initiated' => 'pending',
            'paid' => 'paid',
            'in_transit' => 'in_transit',
            'received_lagos' => 'in_transit',
            'dispatch_assigned' => 'in_transit',
            'dispatched' => 'in_transit',
            'delivered' => 'delivered',
            'shipment_delay' => 'on_hold',
            'shipment_on_hold' => 'on_hold',
            'undergoing_customs_clearance' => 'on_hold',
            'initiating' => 'pending',
        ],
    ];

    public static function normalize(string $provider, string $status): string
    {
        return static::$map[strtolower($provider)][$status] ?? 'unknown';
    }
}