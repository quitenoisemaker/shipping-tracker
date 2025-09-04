<?php

namespace Quitenoisemaker\ShippingTracker\Listeners;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Quitenoisemaker\ShippingTracker\Models\Shipment;
use Quitenoisemaker\ShippingTracker\Support\StatusMapper;
use Quitenoisemaker\ShippingTracker\Models\ShippingWebhook;
use Quitenoisemaker\ShippingTracker\Events\ShippingWebhookReceived;

class HandleShippingWebhook implements ShouldQueue
{
    public $tries = 3;
    public $backoff = [60, 300, 600];

    public function handle(ShippingWebhookReceived $event): void
    {
        $webhook = $event->webhook;
        $provider = strtolower($webhook->provider);

        try {
            match ($provider) {
                'sendbox' => $this->handleSendbox($webhook),
                'cargoplug' => $this->handleCargoplug($webhook),
                'dhl' => $this->handleDHL($webhook),
                default => Log::warning("Unhandled webhook provider: {$provider}"),
            };
        } catch (\Exception $e) {
            Log::error("Webhook processing failed for {$provider}", [
                'error' => $e->getMessage(),
                'payload' => $webhook->payload,
            ]);
            throw $e;
        }
    }

    protected function handleSendbox(ShippingWebhook $webhook): void
    {
        $payload = $webhook->payload;
        $trackingNumber = $payload['code'] ?? null;
        $rawStatus = $payload['status']['code'] ?? null;

        if ($trackingNumber && $rawStatus) {
            try {
                $status = StatusMapper::normalize('sendbox', $rawStatus);
                Shipment::updateOrCreate(
                    ['tracking_number' => $trackingNumber, 'provider' => 'sendbox'],
                    [
                        'status' => $status,
                        'location' => $payload['events'][0]['location_description'] ?? null,
                        'estimated_delivery' => $payload['delivery_eta'] ?? null,
                        'history' => collect($payload['events'] ?? [])->map(function ($event) {
                            $eventStatus = $event['status']['code'] ?? null;
                            return [
                                'timestamp' => $event['timestamp'] ?? null,
                                'location_description' => $event['location_description'] ?? null,
                                'status' => $eventStatus ? StatusMapper::normalize('sendbox', $eventStatus) : null,
                            ];
                        })->toArray(),
                    ]
                );
                Log::info('Sendbox shipment updated', [
                    'tracking_number' => $trackingNumber,
                    'status' => $status,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to update Sendbox shipment', [
                    'tracking_number' => $trackingNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('Invalid Sendbox webhook payload', ['payload' => $payload]);
        }
    }

    protected function handleCargoplug(ShippingWebhook $webhook): void
    {
        $payload = $webhook->payload;
        $trackingNumber = $payload['tracking_number'] ?? null;
        $rawStatus = $payload['status'] ?? null;

        if ($trackingNumber && $rawStatus) {
            try {
                $status = StatusMapper::normalize('cargoplug', $rawStatus);
                Shipment::updateOrCreate(
                    ['tracking_number' => $trackingNumber, 'provider' => 'cargoplug'],
                    [
                        'status' => $status,
                        'location' => $payload['location'] ?? null,
                        'estimated_delivery' => $payload['expected_delivery_date'] ?? null,
                        'history' => collect($payload['history'] ?? [])->map(function ($event) {
                            $eventStatus = $event['status'] ?? null;
                            return [
                                'timestamp' => $event['order_updated'] ?? null,
                                'status' => $eventStatus ? StatusMapper::normalize('cargoplug', $eventStatus) : null,
                            ];
                        })->toArray(),
                    ]
                );
                Log::info('Cargoplug shipment updated', [
                    'tracking_number' => $trackingNumber,
                    'status' => $status,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to update Cargoplug shipment', [
                    'tracking_number' => $trackingNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('Invalid Cargoplug webhook payload', ['payload' => $payload]);
        }
    }

    protected function handleDHL(ShippingWebhook $webhook): void
    {
        $payload = $webhook->payload;
        $shipmentData = $payload['shipments'][0] ?? null;
        $trackingNumber = $shipmentData['id'] ?? null;
        $rawStatus = $shipmentData['status']['statusCode'] ?? null;

        if ($trackingNumber && $rawStatus) {
            try {
                $status = StatusMapper::normalize('dhl', $rawStatus);

                // Prepare the new event
                $newEvent = [
                    'timestamp' => isset($shipmentData['status']['timestamp'])
                        ? date('Y-m-d H:i:s', strtotime($shipmentData['status']['timestamp']))
                        : null,
                    'status' => isset($shipmentData['status']['status'])
                        ? StatusMapper::normalize('dhl', $shipmentData['status']['statusCode'])
                        : null,
                    'location_description' => $shipmentData['status']['location']['address']['addressLocality'] ?? null,
                    'description' => $shipmentData['status']['description'] ?? null,
                ];

                // Fetch existing history (already stored in DB, if any)
                $existingHistory = Shipment::where('tracking_number', $trackingNumber)
                    ->where('provider', 'dhl')
                    ->value('history') ?? [];

                // Ensure it's an array (json column cast can sometimes return null)
                if (!is_array($existingHistory)) {
                    $existingHistory = [];
                }

                // Append new event if it’s not already the last one
                $history = $existingHistory;
                $lastEvent = end($history);
                if ($lastEvent !== $newEvent) {
                    $history[] = $newEvent;
                }

                Shipment::updateOrCreate(
                    ['tracking_number' => $trackingNumber, 'provider' => 'dhl'],
                    [
                        'status' => $status,
                        'location' => $shipmentData['status']['location']['address']['addressLocality'] ?? null,
                        'estimated_delivery' => $shipmentData['estimatedDeliveryDate'] ?? null,
                        'history' => $history,
                    ]
                );

                Log::info('DHL shipment updated', [
                    'tracking_number' => $trackingNumber,
                    'status' => $status,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to update DHL shipment', [
                    'tracking_number' => $trackingNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('Invalid DHL webhook payload', ['payload' => $payload]);
        }
    }
}
