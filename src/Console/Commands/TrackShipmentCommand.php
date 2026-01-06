<?php

namespace Quitenoisemaker\ShippingTracker\Console\Commands;

use Illuminate\Console\Command;
use Quitenoisemaker\ShippingTracker\Facades\ShippingTracker;
use Quitenoisemaker\ShippingTracker\Exceptions\ShippingException;

class TrackShipmentCommand extends Command
{
    protected $signature = 'shipping:track 
                            {id : The tracking number} 
                            {--provider= : Specific provider to use}';

    protected $description = 'Track a shipment using ShippingTracker';

    public function handle()
    {
        $trackingNumber = $this->argument('id');
        $provider = $this->option('provider');

        $this->info("Tracking shipment: $trackingNumber");
        if ($provider) {
            $this->info("Using provider: $provider");
            ShippingTracker::use($provider);
        }

        try {
            $result = ShippingTracker::track($trackingNumber);

            $this->table(
                ['Key', 'Value'],
                [
                    ['Tracking Number', $result->trackingNumber],
                    ['Status', $result->status],
                    ['Provider', $result->provider],
                    ['Estimated Delivery', $result->estimatedDelivery?->toDateTimeString() ?? 'N/A'],
                    ['Description', $result->description ?? 'N/A'],
                ]
            );

            if ($result->events->isNotEmpty()) {
                $this->newLine();
                $this->info('Events:');
                $this->table(
                    ['Status', 'Description', 'Location', 'Timestamp'],
                    $result->events->map(fn($e) => [
                        $e->status,
                        $e->description,
                        $e->location,
                        $e->timestamp?->toDateTimeString()
                    ])->toArray()
                );
            }

        } catch (ShippingException $e) {
            $this->error($e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->error("Unexpected error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
