<?php

namespace Quitenoisemaker\ShippingTracker\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = [
        'tracking_number',
        'provider',
        'status',
        'location',
        'estimated_delivery',
        'history',
    ];

    protected $casts = [
        'history' => 'array',
    ];
}