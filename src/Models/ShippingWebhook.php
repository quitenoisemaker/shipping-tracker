<?php

namespace Quitenoisemaker\ShippingTracker\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingWebhook extends Model
{
    protected $fillable = [
        'provider',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}