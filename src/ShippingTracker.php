<?php

namespace Quitenoisemaker\ShippingTracker;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Quitenoisemaker\ShippingTracker\Models\Shipment;
use Quitenoisemaker\ShippingTracker\Exceptions\ShippingException;
use Quitenoisemaker\ShippingTracker\Contracts\ShippingProviderInterface;

class ShippingTracker
{
    protected ?string $selectedProviderKey = null;
    protected ?ShippingProviderInterface $provider = null;

    public function __construct()
    {
        $this->selectedProviderKey = config('shipping-tracker.default');
    }

    public function use(string $providerKey): self
    {
        $this->selectedProviderKey = $providerKey;
        $this->provider = null; // Reset provider instance to force re-resolution if changed
        return $this;
    }

    protected function resolveSelectedProvider(): void
    {
        if ($this->provider) {
            return;
        }

        if (!$this->selectedProviderKey) {
            return;
        }
        
        $providers = config('shipping-tracker.providers', []);

        if (!isset($providers[$this->selectedProviderKey])) {
            throw new \Exception("Shipping provider [{$this->selectedProviderKey}] not configured.");
        }

        try {
            $this->provider = app($providers[$this->selectedProviderKey]);
        } catch (\Exception $e) {
            throw new ShippingException("Failed to initialize provider [{$this->selectedProviderKey}]: {$e->getMessage()}");
        }
    }

    public function track(string $trackingNumber): \Quitenoisemaker\ShippingTracker\DTOs\TrackingResult
    {
        // Try to instantiate the selected provider (default or explicit)
        if ($this->selectedProviderKey) {
            try {
                $this->resolveSelectedProvider();
            } catch (\Exception $e) {
                 Log::warning("Provider [{$this->selectedProviderKey}] failed to initialize or track: {$e->getMessage()}");
            }
        }

        // If provider is ready, use it
        if ($this->provider) {
             try {
                return $this->provider->track($trackingNumber);
             } catch (\Exception $e) {
                Log::warning("Provider [" . get_class($this->provider) . "] failed for tracking number [$trackingNumber]: {$e->getMessage()}");
                // If it fails, fall through to auto-resolve
             }
        }

        return $this->resolveProvider($trackingNumber);
    }

    protected function resolveProvider(string $trackingNumber): \Quitenoisemaker\ShippingTracker\DTOs\TrackingResult
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
                // We do NOT set $this->provider here permanently effectively, 
                // or maybe we should? Original code did.
                $this->provider = $provider; 
                $this->selectedProviderKey = $cachedProviderKey;
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
            // Skip the one we already tried if it matched selectedProviderKey
            if ($this->selectedProviderKey === $key && $this->provider) {
                 // Actually if $this->provider is set, we wouldn't be here unless it failed.
                 // So we should surely skip it to avoid retry loop if we just tried it.
                 continue;
            }

            try {
                $provider = app()->make($providerClass);
                $result = $provider->track($trackingNumber);
                
                $this->provider = $provider;
                $this->selectedProviderKey = $key;
                
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
        $this->resolveSelectedProvider(); // Ensure provider is ready
        if (!$this->provider) {
            throw new ShippingException('No provider selected for webhook handling.');
        }
        $this->provider->handleWebhook($payload);
    }

    public function getProvider(): ?ShippingProviderInterface
    {
        try {
            $this->resolveSelectedProvider();
        } catch (\Exception $e) {
            // ignore if just getting
        }
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
  
        return $results;
    }

    public function getHistory(string $trackingNumber): ?Shipment
    {
        return Shipment::where('tracking_number', $trackingNumber)->first();
    }
}
