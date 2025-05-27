<?php

namespace Quitenoisemaker\ShippingTracker\Support;

use Illuminate\Support\Arr;

class TrackingResponseFormatter
{
    public static function formatSendbox(array $data): array
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Sendbox response data is empty');
        }
        
        return [
            'status' => Arr::get($data, 'status_code', 'unknown'),
            'current_location' => Arr::get($data, 'events.0.location_description', 'Unknown'),
            'estimated_delivery' => $data['events.delivery_eta'] ?? null,
            'events' => collect($data['events'] ?? [])->map(function ($event) {
                return [
                    'timestamp' => $event['date_created'] ?? null,
                    'description' => $event['description'] ?? '',
                    'location' => $event['location_description'] ?? null,
                    'status' => $event['status']['code'] ?? null,
                ];
            })->toArray(),
            'raw' => $data,
        ];
    }

    public static function formatCargoplug(array $data): array
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Cargoplug response data is empty');
        }
        return [
            'status' => Arr::get($data, 'status', 'unknown'),
            'description' => Arr::get($data, 'description', null),
            'tracking_number' => Arr::get($data, 'tracking_number', null),
            'weight' => Arr::get($data, 'weight', null),
            'quantity' => Arr::get($data, 'quantity', null),
            'estimated_delivery' => $data['expected_delivery_date'] ?? null,
            'events' => collect($data['history'] ?? [])->map(function ($event) {
                return [
                    'timestamp' => $event['order_updated'] ?? null,
                    'status' => $event['status'] ?? null,
                ];
            })->toArray(),
            'raw' => $data,
        ];
    }
}
