<?php

namespace Quitenoisemaker\ShippingTracker\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Quitenoisemaker\ShippingTracker\Exceptions\ShippingException;
use Quitenoisemaker\ShippingTracker\Support\TrackingResponseFormatter;
use Quitenoisemaker\ShippingTracker\Contracts\ShippingProviderInterface;

class CargoplugShippingProvider implements ShippingProviderInterface
{
    protected ?string $baseUrl = null;
    protected ?string $secretKey = null;
    protected ?string $clientKey = null;
    protected ?string $token = null;

    public function __construct()
    {
        $baseUrl = config('shipping-tracker.cargoplug.base_url');
        $secretKey = config('shipping-tracker.cargoplug.secret_key');
        $clientKey = config('shipping-tracker.cargoplug.client_key');

        if (!$baseUrl) {
            throw new ShippingException('Cargoplug base URL is not configured.');
        }
        if (!$secretKey || !$clientKey) {
            throw new ShippingException('Cargoplug secret key or client key is not configured.');
        }

        $this->baseUrl = $baseUrl;
        $this->secretKey = $secretKey;
        $this->clientKey = $clientKey;

        $this->token = $this->authenticate();
    }

    protected function authenticate(): string
    {
        return Cache::remember('cargoplug_access_token', now()->addHour(), function () {
            $response = Http::retry(3, 100)->post("{$this->baseUrl}/user/authenticate", [
                'secret_key' => $this->secretKey,
                'client_key' => $this->clientKey,
            ]);

            if ($response->failed()) {
                throw new ShippingException('Cargoplug authentication failed: ' . ($response->json('message') ?? 'HTTP ' . $response->status()));
            }

            $accessToken = $response->json('data.access_token');
            if (empty($accessToken)) {
                throw new ShippingException('Cargoplug access token missing in response: ' . ($response->json('message') ?? 'Invalid response structure'));
            }

            return $accessToken;
        });
    }

    public function track(string $trackingNumber): array
    {
        try {
            $response = Http::withToken($this->token)
                ->post("{$this->baseUrl}/shipment/tracking/external", [
                    'tracking_number' => $trackingNumber,
                ]);

            if ($response->failed() || empty($response->json('data'))) {
                throw new ShippingException('Cargoplug tracking failed: ' . ($response->json('message') ?? 'Unknown error'));
            }

            $result = $response->json('data.0.result');
            return TrackingResponseFormatter::formatCargoplug($result);
        } catch (\Throwable $th) {
            Log::error('Cargoplug tracking error', [
                'tracking_number' => $trackingNumber,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    public function handleWebhook(array $payload): void
    {
        $trackingNumber = $payload['tracking_number'] ?? null;
        $status = $payload['status'] ?? null;

        if ($trackingNumber && $status) {
            Log::info('Cargoplug webhook processed', ['payload' => $payload]);
        } else {
            Log::warning('Invalid Cargoplug webhook payload', ['payload' => $payload]);
        }
    }

    public function supports(string $trackingNumber): bool
    {
        // Cargoplug does not use specific tracking number formats.
        // Future providers may implement prefix or regex-based validation here.
        return true;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }
}
