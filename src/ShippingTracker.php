<?php

namespace Quitenoisemaker\ShippingTracker;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Quitenoisemaker\ShippingTracker\Models\Shipment;
use Quitenoisemaker\ShippingTracker\Exceptions\ShippingException;
use Quitenoisemaker\ShippingTracker\Contracts\ShippingProviderInterface;

class ShippingTracker
{
    protected ?ShippingProviderInterface $provider = null;

    public function __construct()
    {
        $this->setProvider(config('shipping-tracker.default'));
    }

    public function use(string $providerKey): self
    {
        $this->setProvider($providerKey);
        return $this;
    }

    protected function setProvider(?string $key): void
    {
        if (!$key) {
            return;
        }
        $providers = config('shipping-tracker.providers', []);

        if (!isset($providers[$key])) {
            throw new \Exception("Shipping provider [$key] not configured.");
        }
        try {
            $this->provider = app($providers[$key]);
        } catch (\Exception $e) {
            throw new ShippingException("Failed to initialize provider [$key]: {$e->getMessage()}");
        }
    }

    public function track(string $trackingNumber): array
    {
        if (!$this->provider) {
            return $this->resolveProvider($trackingNumber);
        }

        try {
            return $this->provider->track($trackingNumber);
        } catch (\Exception $e) {
            Log::warning("Pre-selected provider [" . get_class($this->provider) . "] failed for tracking number [$trackingNumber]: {$e->getMessage()}");
            return $this->resolveProvider($trackingNumber);
        }
    }

    protected function resolveProvider(string $trackingNumber): array
    {
        $providers = config('shipping-tracker.providers', []);
        $triedProviders = [];
        $errors = [];

        // Check cache for known provider
        $cacheKey = "shipping_tracker_provider_{$trackingNumber}";
        $cachedProviderKey = Cache::get($cacheKey);

        if ($cachedProviderKey && isset($providers[$cachedProviderKey])) {
            try {
                $provider = app()->make($providers[$cachedProviderKey]);
                $result = $provider->track($trackingNumber);
                $this->provider = $provider;
                return $result;
            } catch (\Exception $e) {
                Log::warning("Cached provider [$cachedProviderKey] failed for tracking number [$trackingNumber]: {$e->getMessage()}");
                Cache::forget($cacheKey); // Clear cache on failure
            }
        }

        foreach ($providers as $key => $providerClass) {
            if ($cachedProviderKey === $key) {
                continue; // Skip if already tried via cache
            }
            try {
                $provider = app()->make($providerClass);
                $result = $provider->track($trackingNumber);
                $this->provider = $provider;
                Cache::put($cacheKey, $key, now()->addDays(7)); // Cache for 7 days
                Log::info('Provider resolved for tracking number', [
                    'tracking_number' => $trackingNumber,
                    'provider' => $key,
                ]);
                return $result;
            } catch (\Exception $e) {
                Log::warning("Provider [$key] failed for tracking number [$trackingNumber]: {$e->getMessage()}");
                $triedProviders[] = $key;
                $errors[$key] = $e->getMessage();
                continue;
            }
        }

        throw new ShippingException(
            "No provider supports tracking number: {$trackingNumber}. Tried: " . implode(', ', $triedProviders)
        );
    }

    public function handleWebhook(array $payload): void
    {
        if (!$this->provider) {
            throw new ShippingException('No provider selected for webhook handling.');
        }
        $this->provider->handleWebhook($payload);
    }

    public function getProvider(): ?ShippingProviderInterface
    {
        return $this->provider;
    }

    public function trackMultiple(array $trackingNumbers): array
    {
        $results = [];
        foreach ($trackingNumbers as $trackingNumber) {
            try {
                $results[$trackingNumber] = $this->track($trackingNumber);
            } catch (ShippingException $e) {
                Log::error('Batch tracking failed', [
                    'tracking_number' => $trackingNumber,
                    'error' => $e->getMessage(),
                ]);
                $results[$trackingNumber] = ['error' => $e->getMessage()];
            }
        }
        //$results = ShippingTracker::trackMultiple(['SB123456789', 'CP987654321']);
        return $results;
    }

    public function getHistory(string $trackingNumber): ?Shipment
    {
        return Shipment::where('tracking_number', $trackingNumber)->first();
    }
}
