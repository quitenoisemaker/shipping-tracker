# ShippingTracker

[![CI Pipeline](https://github.com/quitenoisemaker/shipping-tracker/actions/workflows/test.yml/badge.svg)](https://github.com/quitenoisemaker/shipping-tracker/actions/workflows/test.yml)

A Laravel package to simplify shipment tracking and webhook handling for Africa and Europe logistics. Supports providers like Cargoplug, Sendbox, and DHL, with an open-source spirit welcoming contributions.

## Features
- Track shipments with a unified interface.
- Handle webhooks from couriers effortlessly.
- Store tracking history in a `shippings` table.
- Queue webhook processing with the `database` driver.
- Extensible for custom providers.
- Normalize provider-specific statuses (e.g., "In transit" -> `in_transit`)

## Requirements
- PHP 8.1, 8.2, or 8.3
- Laravel 10 or 11
- Composer

## Installation
1. Install via Composer:
   ```bash
   composer require quitenoisemaker/shipping-tracker
   ```
2. Publish the configuration and migrations:
   ```bash
   php artisan vendor:publish --provider="Quitenoisemaker\ShippingTracker\ShippingTrackerServiceProvider"
   ```
3. Run migrations to create the `shipping_webhooks` and `jobs` tables:
   ```bash
   php artisan migrate
   ```

4. **Add Environment Variables**

   Update your `.env` file:

   ```env
   SENDBOX_API_URL=https://live.sendbox.co
   SENDBOX_APP_ID=your_app_id
   SENDBOX_APP_CLIENT_KEY=your_client_key

   CARGOPLUG_API_URL=https://api.getcargoplug.com/api/v1
   CARGOPLUG_SECRET_KEY=your_secret_key
   CARGOPLUG_CLIENT_KEY=your_client_key

   DHL_BASE_URL=https://api-eu.dhl.com
   DHL_API_KEY=your_api_key
   ```

## Configuration

The `config/shipping-tracker.php` file allows you to:

- Set the default provider (e.g., `dhl`).
- Define provider classes and their API credentials.
- Add new providers by mapping keys to their implementation classes.

**Note**: Providers like (Sendbox and Cargoplug) do not use tracking number prefixes or webhook signatures. The package caches successful provider matches for known tracking numbers to optimize tracking. New tracking numbers trigger a full provider search, and explicit provider selection via `use()` is respected without affecting the cache.

Example:

```php
return [
    'default' => 'sendbox',
    'providers' => [
        'sendbox' => \Quitenoisemaker\ShippingTracker\Providers\SendboxShippingProvider::class,
        'cargoplug' => \Quitenoisemaker\ShippingTracker\Providers\CargoplugShippingProvider::class,
    ],
    'sendbox' => [
        'base_url' => env('SENDBOX_API_URL', 'https://live.sendbox.co'),
        'app_id' => env('SENDBOX_APP_ID'),
        'client_key' => env('SENDBOX_APP_CLIENT_KEY'),
    ],
    'cargoplug' => [
        'base_url' => env('CARGOPLUG_API_URL', 'https://api.getcargoplug.com/api/v1'),
        'secret_key' => env('CARGOPLUG_SECRET_KEY'),
        'client_key' => env('CARGOPLUG_CLIENT_KEY'),
    ],
    'dhl' => [
        'base_url' => env('DHL_BASE_URL', 'https://api-eu.dhl.com'),
        'api_key' => env('DHL_API_KEY')
    ],
];
```

## Usage

### Tracking a Shipment

Track a single shipment. If no provider is specified, the package tries all configured providers, caching the successful provider for the tracking number. New tracking numbers trigger a full provider search.

```php
use Quitenoisemaker\ShippingTracker\facades\ShippingTracker;

try {
    $result = ShippingTracker::track('SB123456789');
    dd($result);
} catch (\Quitenoisemaker\ShippingTracker\Exceptions\ShippingException $e) {
    \Log::error('Tracking failed: ' . $e->getMessage());
}
```

Explicitly specify a provider to bypass automatic resolution and caching:

```php
$result = ShippingTracker::use('cargoplug')->track('CP987654321');
```

The response includes: `tracking_number`, `status`, `location`, `estimated_delivery`, `events`, and `raw`.

### Tracking Multiple Shipments

Track multiple shipments in one call (client-side, as providers do not support batch API calls):

```php
try {
    $results = ShippingTracker::trackMultiple(['SB123456789', 'CP987654321']);
    dd($results);
} catch (\Quitenoisemaker\ShippingTracker\Exceptions\ShippingException $e) {
    \Log::error('Batch tracking failed: ' . $e->getMessage());
}
```

The response is an array keyed by tracking number, with results or error messages.

### Tracking History

Shipment updates from webhooks are stored in the `shipments` table, including `tracking_number`, `provider`, `status`, `location`, `estimated_delivery`, and `history`.

Retrieve a shipment’s history:

```php
use Quitenoisemaker\ShippingTracker\Models\Shipment;

$shipment = Shipment::where('tracking_number', 'SB123456789')->first();
dd($shipment->history);
```

Or use the convenience method:

```php
$history = ShippingTracker::getHistory('SB123456789');
dd($history->history);
```

### Handling Webhooks

Webhooks are validated for required fields.
 
  Default required fields for Cargoplug and Sendbox:
    - tracking_number
    - status
 
  DHL specific required field:
    - self (the subscription URL, always starting with https://api-eu.dhl.com)
    - scope 
 
  The package is extensible for signature-based validation for providers that support webhook signing.



```php
Route::prefix('api/shipping')->group(function () {
    Route::post('webhooks/{provider}', [\Quitenoisemaker\ShippingTracker\Http\Controllers\WebhookController::class, 'handle'])->name('shipping.webhook');
});
```

Register the webhook URL (e.g., `https://your-app.com/api/shipping/webhooks/sendbox`) with your provider.

## Status Normalization
ShippingTracker normalizes provider statuses using `StatusMapper` in both webhooks and API tracking (`track` method). It handles case and space variations (e.g., Cargoplug's "In Transit" → `in_transit`, "PAID" → `paid`). Examples:
- **Sendbox**: `delivery_started` → `in_transit`, `delivered` → `delivered`
- **Cargoplug**: `received_abroad` → `in_transit`, `In Transit` → `in_transit`, `delivered` → `delivered`
- **DHL**: `CUSTOMER PICKUP` → `delivered`, `PACKAGE SCREENED SUCCESSFULLY` → `in_transit`, `ATTEMPTED DELIVERY` → `pending`
Customize mappings in `config/shipping-tracker.php` under `status_map`.

## Extending the Package
Create a custom provider:
1. Create a class implementing `Quitenoisemaker\ShippingTracker\Contracts\ShippingProvider`:
   ```php
   namespace App\ShippingProviders;

   use Quitenoisemaker\ShippingTracker\Contracts\ShippingProvider;

   class CustomProvider implements ShippingProvider
   {
       public function track(string $trackingNumber): array
       {
           // API call to your provider
           return ['tracking_number' => $trackingNumber, 'status' => 'in_transit'];
       }

       public function handleWebhook(array $payload): void
       {
           // Process webhook data
       }
   }
   ```
2. Register it in `config/shipping-tracker.php`:
   ```php
   'providers' => [
       'custom' => [
           'class' => \App\ShippingProviders\CustomProvider::class,
       ],
   ];
   ```
3. Use it:
   ```php
   $tracker->use('custom')->track('CUSTOM123');
   ```

## Troubleshooting
- **API Credential Errors**:
  - Ensure `CARGOPLUG_API_KEY` and `SENDBOX_API_KEY` are set in `.env`.
  - Verify keys with your provider’s dashboard.
  - Test with: `php artisan tinker` and `$tracker->use('cargoplug')->track('test_number')`.
- **Webhook Not Received**:
  - Confirm the webhook URL is correct (e.g., `your-app.com/shipping/webhook`).
  - Check `shipping_webhooks` table for entries.
  - Ensure the queue worker is running: `php artisan queue:work`.
  - Verify your server allows POST requests (e.g., no firewall blocks).
- **Queue Issues**:
  - Ensure `QUEUE_CONNECTION=database` in `.env`.
  - Check `jobs` table exists: `php artisan migrate`.
  - Clear cache: `php artisan config:cache`.
- **Test Failures**:
  - Run `composer install` to ensure dependencies.
  - Check `phpunit.xml` for correct database settings.
  - Share errors with the community (see **Contributing**).

## Testing

Run tests with:

```bash
php artisan test
```

## Changelog

### v1.2.0 (Current)

- Added DHL integration for tracking and webhook handling.
- Normalized DHL statuses (e.g., "DELIVERED" → `delivered`, "OUT FOR DELIVERY" → `in_transit`).

### v1.0.0
- Initial release.
- Supports Cargoplug and Sendbox providers.
- Features tracking, webhook handling, and history storage.
- Uses `database` queue driver with `jobs` and `shipping_webhooks` tables.

Future updates (e.g., DHL support) will be listed here.

## Contributing
We love open source! Join us by contributing at [github.com/quitenoisemaker/shipping-tracker](https://github.com/quitenoisemaker/shipping-tracker). See [CONTRIBUTING.md](CONTRIBUTING.md) for how to submit issues or pull requests.

## License
MIT License. See [LICENSE](LICENSE) for details.