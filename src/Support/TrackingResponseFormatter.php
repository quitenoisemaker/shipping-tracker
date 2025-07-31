<?php

namespace Quitenoisemaker\ShippingTracker\Support;

use Illuminate\Support\Arr;

class TrackingResponseFormatter
{
    /**
     * Format a Sendbox tracking response into a standardized format.
     *
     * @param array $data
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function formatSendbox(array $data): array
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Sendbox response data is empty');
        }

        $rawStatus = Arr::get($data, 'status_code', 'unknown');
        $status = StatusMapper::normalize('sendbox', $rawStatus);

        return [
            'status' => $status,
            'current_location' => Arr::get($data, 'events.0.location_description', 'Unknown'),
            'estimated_delivery' => $data['events.delivery_eta'] ?? null,
            'events' => collect($data['events'] ?? [])->map(function ($event) {
                $eventStatus = $event['status']['code'] ?? null;
                return [
                    'timestamp' => $event['date_created'] ?? null,
                    'description' => $event['description'] ?? '',
                    'location' => $event['location_description'] ?? null,
                    'status' => $eventStatus ? StatusMapper::normalize('sendbox', $eventStatus) : null,
                ];
            })->toArray(),
            'raw' => $data,
        ];
    }

    /**
     * Format a Cargoplug tracking response into a standardized format.
     *
     * @param array $data
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function formatCargoplug(array $data): array
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Cargoplug response data is empty');
        }

        $rawStatus = Arr::get($data, 'status', 'unknown');
        $status = StatusMapper::normalize('cargoplug', $rawStatus);

        return [
            'status' => $status,
            'description' => Arr::get($data, 'description', null),
            'tracking_number' => Arr::get($data, 'tracking_number', null),
            'weight' => Arr::get($data, 'weight', null),
            'quantity' => Arr::get($data, 'quantity', null),
            'estimated_delivery' => $data['expected_delivery_date'] ?? null,
            'events' => collect($data['history'] ?? [])->map(function ($event) {
                $eventStatus = $event['status'] ?? null;
                return [
                    'timestamp' => $event['order_updated'] ?? null,
                    'status' => $eventStatus ? StatusMapper::normalize('cargoplug', $eventStatus) : null,
                ];
            })->toArray(),
            'raw' => $data,
        ];
    }
}
