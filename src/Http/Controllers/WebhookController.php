<?php

namespace Quitenoisemaker\ShippingTracker\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Quitenoisemaker\ShippingTracker\ShippingTracker;
use Quitenoisemaker\ShippingTracker\Models\ShippingWebhook;
use Quitenoisemaker\ShippingTracker\Exceptions\ShippingException;
use Quitenoisemaker\ShippingTracker\Events\ShippingWebhookReceived;

class WebhookController extends Controller
{
    public function handle(Request $request, string $provider)
    {
        try {
            // This is a rate limiter that will only allow 100 requests per minute per IP.
            // This is to prevent abuse from a single IP.
            // If the limit is reached, we will return a 429 status code (Too Many Requests).
            $executed = RateLimiter::attempt(
                'webhook:' . $request->ip(), // The key for the rate limiter
                100, // The number of requests allowed per minute
                function () use ($request, $provider) {

                    // Validate the webhook payload
                    // This will throw an exception if the payload is invalid
                    $this->validateWebhook($request, $provider);

                    // Create a webhook model and save it to the database
                    $webhook = ShippingWebhook::create([
                        'provider' => $provider,
                        'payload' => $request->all(),
                    ]);

                    // Fire an event so that the webhook can be processed
                    // This will call the handleWebhook method on the shipping provider
                    event(new ShippingWebhookReceived($webhook));

                    // Process the webhook using the shipping provider
                    app(ShippingTracker::class)->use($provider)->handleWebhook($request->all());
                },
                60 // The number of seconds to wait before retrying
            );

            if (!$executed) {
                // If the rate limit is reached, return a 429 status code
                return response()->json(['message' => 'Too many requests'], 429);
            }

        } catch (\Exception $e) {
            // If the webhook processing fails, log the error and return a 400 status code
            Log::error("Webhook processing failed for {$provider}: {$e->getMessage()}");
            return response()->json(['message' => "Invalid provider: {$provider}"], 400);
        }

        // If everything is successful, return a 200 status code
        return response()->json(['message' => 'Webhook received'], 200);
    }

    protected function validateWebhook(Request $request, string $provider): void
    {
        $providers = array_keys(config('shipping-tracker.providers', []));
        if (!in_array($provider, $providers, true)) {
            throw new ShippingException("Invalid provider: {$provider}");
        }

        $requiredFields = config("shipping-tracker.{$provider}.required_webhook_fields", []);
        $payload = $request->all();

        foreach ($requiredFields as $field) {
            if (data_get($payload, $field) === null || data_get($payload, $field) === '') {
                throw new ShippingException("Webhook payload missing required field: {$field}");
            }
        }

        // Example for future providers with signatures
        // if ($provider === 'dhl') {
        //     $signature = $request->header('X-Dhl-Signature');
        //     $expected = hash_hmac('sha256', $request->getContent(), config('shipping-tracker.dhl.api_key'));
        //     if (!hash_equals($expected, $signature)) {
        //         throw new ShippingException('Invalid DHL webhook signature');
        //     }
        // }
    }
}
