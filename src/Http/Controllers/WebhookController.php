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
            // Rate limit to 100 requests per minute per IP
            $executed = RateLimiter::attempt(
                'webhook:' . $request->ip(),
                100,
                function () use ($request, $provider) {

                    $this->validateWebhook($request, $provider);

                    $webhook = ShippingWebhook::create([
                        'provider' => $provider,
                        'payload' => $request->all(),
                    ]);

                    event(new ShippingWebhookReceived($webhook));


                    app(ShippingTracker::class)->use($provider)->handleWebhook($request->all());
                },
                60
            );

            if (!$executed) {
                return response()->json(['message' => 'Too many requests'], 429);
            }

        } catch (\Exception $e) {
            Log::error("Webhook processing failed for {$provider}: {$e->getMessage()}");
            return response()->json(['message' => "Invalid provider: {$provider}"], 400);
        }

        return response()->json(['message' => 'Webhook received'], 200);
    }

    protected function validateWebhook(Request $request, string $provider): void
    {
        $providers = array_keys(config('shipping-tracker.providers', []));
        if (!in_array($provider, $providers, true)) {
            throw new ShippingException("Invalid provider: {$provider}");
        }

        $payload = $request->all();
        if (empty($payload['tracking_number']) || empty($payload['status'])) {
            throw new ShippingException('Webhook payload missing required fields: tracking_number, status');
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
