<?php


namespace Quitenoisemaker\ShippingTracker\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Quitenoisemaker\ShippingTracker\Exceptions\ShippingException;
use Quitenoisemaker\ShippingTracker\Support\TrackingResponseFormatter;
use Quitenoisemaker\ShippingTracker\Contracts\ShippingProviderInterface;


class DhlShippingProvider implements ShippingProviderInterface
{

    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('shipping-tracker.dhl.base_url');
        $this->apiKey = config('shipping-tracker.dhl.api_key');
    }

    public function track(string $trackingNumber): array
    {
        try {
            $response = Http::withHeaders(['DHL-API-Key' => $this->apiKey])
                ->get("{$this->baseUrl}/track/shipments", [
                    'trackingNumber' => $trackingNumber,
                ]);
            if ($response->failed()) {
                throw new ShippingException('DHL tracking failed');
            }
            $result = $response->json('shipments.0');
            return TrackingResponseFormatter::formatDHL($result);
        } catch (\Throwable $th) {
            Log::error('DHL tracking error', [
                'tracking_number' => $trackingNumber,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    public function handleWebhook(array $payload): void
    {
        // Validate or process payload  
        $payload = $payload['shipments'][0];
        if (empty($payload['id']) || empty($payload['status']['statusCode'])) {
            Log::warning('Invalid DHL webhook payload', ['payload' => $payload]);
            return;
        }
        Log::info('DHL webhook received', ['payload' => $payload]);
    }

    /**
     * Checks if the given tracking number is supported by this provider.
     *
     * According to DHL's documentation, their tracking numbers are typically 10-39 characters long and can include
     * letters and numbers.
     *
     * @param string $trackingNumber
     * @return bool
     */
    public function supports(string $trackingNumber): bool
    {
        // The regular expression pattern below matches strings that are:
        // - between 10 and 39 characters long (inclusive)
        // - contain only uppercase and lowercase letters and digits
        return preg_match('/^[A-Z0-9]{10,39}$/i', $trackingNumber) === 1;
    }
}
