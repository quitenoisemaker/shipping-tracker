<?php

namespace Quitenoisemaker\ShippingTracker\Support;

use Illuminate\Support\Arr;
use Quitenoisemaker\ShippingTracker\DTOs\TrackingResult;
use Quitenoisemaker\ShippingTracker\DTOs\TrackingEvent;
use Carbon\Carbon;

class TrackingResponseFormatter
{
    /**
     * Format a Sendbox tracking response into a standardized format.
     *
     * @param array $data
     * @return TrackingResult
     * @throws \InvalidArgumentException
     */
    /**
     * Format a Sendbox tracking response into a standardized format.
     *
     * @param array $data
     * @param string|null $trackingNumber
     * @return TrackingResult
     * @throws \InvalidArgumentException
     */
    public static function formatSendbox(array $data, ?string $trackingNumber = null): TrackingResult
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Sendbox response data is empty');
        }

        $rawStatus = Arr::get($data, 'status_code', 'unknown');
        $status = StatusMapper::normalize('sendbox', $rawStatus);

        $events = collect($data['events'] ?? [])->map(function ($event) {
            $eventStatus = $event['status']['code'] ?? null;
            return new TrackingEvent(
                status: $eventStatus ? StatusMapper::normalize('sendbox', $eventStatus) : null,
                description: $event['description'] ?? '',
                location: $event['location_description'] ?? null,
                timestamp: isset($event['date_created']) ? Carbon::parse($event['date_created']) : null
            );
        });

        // Use provided tracking number, fallback to 'code' in response, fallback to null
        $finalTrackingNumber = $trackingNumber ?? Arr::get($data, 'code');

        return new TrackingResult(
            trackingNumber: $finalTrackingNumber,
            status: $status,
            provider: 'sendbox',
            description: null,
            estimatedDelivery: isset($data['delivery_eta']) ? Carbon::parse($data['delivery_eta']) : null,
            events: $events,
            raw: $data
        );
    }

    /**
     * Format a Cargoplug tracking response into a standardized format.
     *
     * @param array $data
     * @return TrackingResult
     * @throws \InvalidArgumentException
     */
    public static function formatCargoplug(array $data): TrackingResult
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Cargoplug response data is empty');
        }

        $rawStatus = Arr::get($data, 'status', 'unknown');
        $status = StatusMapper::normalize('cargoplug', $rawStatus);

        $events = collect($data['history'] ?? [])->map(function ($event) {
            $eventStatus = $event['status'] ?? null;
            return new TrackingEvent(
                status: $eventStatus ? StatusMapper::normalize('cargoplug', $eventStatus) : null,
                description: null, // Cargoplug history doesn't seem to have description in previous code
                location: null,
                timestamp: isset($event['order_updated']) ? Carbon::parse($event['order_updated']) : null
            );
        });

        return new TrackingResult(
            trackingNumber: Arr::get($data, 'tracking_number', null),
            status: $status,
            provider: 'cargoplug',
            description: Arr::get($data, 'description', null),
            estimatedDelivery: isset($data['expected_delivery_date']) ? Carbon::parse($data['expected_delivery_date']) : null,
            events: $events,
            raw: $data
        );
    }

    /**
     * Format a DHL tracking response into a standardized format.
     *
     * @param array $data
     * @return TrackingResult
     * @throws \InvalidArgumentException
     */
    public static function formatDHL(array $data): TrackingResult
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('DHL response data is empty');
        }

        // Extract raw status safely
        $rawStatus = $data['status']['statusCode']
            ?? $data['status']['status']
            ?? $data['status']['description']
            ?? 'unknown';

        // Normalize status
        $status = StatusMapper::normalize('dhl', $rawStatus);

        // Build history of events
        $events = collect($data['events'] ?? [])->map(function ($event) {
             $raw = $event['statusCode']
                ?? $event['status']
                ?? $event['description']
                ?? 'unknown';

             return new TrackingEvent(
                 status: StatusMapper::normalize('dhl', $raw),
                 description: $event['description'] ?? null,
                 location: $event['location']['address']['addressLocality']
                    ?? $event['location']['address']['countryCode']
                    ?? null,
                 timestamp: isset($event['timestamp']) ? Carbon::parse($event['timestamp']) : null
             );
        });

        // Latest event determines current location if needed, but TrackingResult has event list.
        return new TrackingResult(
            trackingNumber: $data['id'] ?? null,
            status: $status,
            provider: 'dhl',
            description: null,
            estimatedDelivery: null, // DHL response inspection might reveal this
            events: $events,
            raw: $data
        );
    }
}
