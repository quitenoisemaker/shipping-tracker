<?php

namespace Quitenoisemaker\ShippingTracker\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Quitenoisemaker\ShippingTracker\Exceptions\ShippingException;
use Quitenoisemaker\ShippingTracker\Support\TrackingResponseFormatter;
use Quitenoisemaker\ShippingTracker\Contracts\ShippingProviderInterface;

class SendboxShippingProvider implements ShippingProviderInterface
{
    protected ?string $baseUrl = null;
    protected ?string $token = null;
    protected ?string $appId = null;
    protected ?string $clientKey = null;


    public function __construct()
    {
        $appId = config('shipping-tracker.sendbox.app_id');
        $clientKey = config('shipping-tracker.sendbox.client_key');
        $baseUrl = config('shipping-tracker.sendbox.base_url');

        if (!$baseUrl) {
            throw new ShippingException('Sendbox base URL is not configured.');
        }

        if (!$appId || !$clientKey) {
            throw new ShippingException('Sendbox app_id or client_key is not configured.');
        }

        $this->baseUrl = $baseUrl;
        $this->appId = $appId;
        $this->clientKey = $clientKey;

        $this->token = $this->authenticate();
    }

    protected function authenticate(): string
    {
        return Cache::remember('sendbox_access_token', now()->addHour(), function () {
            $appId = config('shipping-tracker.sendbox.app_id');
            $clientKey = config('shipping-tracker.sendbox.client_key');

            if (empty($appId) || empty($clientKey)) {
                throw new ShippingException('Sendbox app_id or client_key is not configured.');
            }

            $url = "{$this->baseUrl}/oauth/access/{$appId}/refresh?app_id={$appId}&client_secret={$clientKey}";
            $response = Http::get($url);

            if ($response->failed()) {
                throw new ShippingException('Failed to refresh Sendbox access token: ' . ($response->json('message') ?? 'Unknown error'));
            }

            $accessToken = $response->json('access_token');
            if (empty($accessToken)) {
                throw new ShippingException('Sendbox access token is not configured.');
            }

            return $accessToken;
        });
    }

    public function track(string $trackingNumber): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->token,
                'app-id' => config('shipping-tracker.sendbox.app_id')
            ])->post(
                "{$this->baseUrl}/shipping/tracking",
                ['code' => $trackingNumber]
            );

            if ($response->failed()) {
                throw new ShippingException('Sendbox tracking failed: ' . ($response->json('message') ?? 'Unknown error'));
            }
            return TrackingResponseFormatter::formatSendbox($response->json());
        } catch (\Exception $e) {
            Log::error('Sendbox tracking error', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function handleWebhook(array $payload): void
    {
        // Validate or process payload
        if (empty($payload['code']) || empty($payload['status']['code'])) {
            Log::warning('Invalid Sendbox webhook payload', ['payload' => $payload]);
            return;
        }
        Log::info('Sendbox webhook processed', ['payload' => $payload]);
    }

    public function supports(string $trackingNumber): bool
    {
        // Sendbox does not use specific tracking number formats.
        // Future providers may implement prefix or regex-based validation here.
        return true;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
