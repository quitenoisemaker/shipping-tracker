<?php

namespace Quitenoisemaker\ShippingTracker\Listeners;

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
                        'location' => $payload['events']['location_description'] ?? null,
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
}
