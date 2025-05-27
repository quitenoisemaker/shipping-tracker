
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Shipping Provider
    |--------------------------------------------------------------------------
    |
    | This is the default provider used when no specific provider is selected.
    |
    */
    'default' => 'sendbox',

    /*
    |--------------------------------------------------------------------------
    | Registered Shipping Providers
    |--------------------------------------------------------------------------
    |
    | Map provider keys to their corresponding implementation classes.
    |
    */
    'providers' => [
        'sendbox' => \Quitenoisemaker\ShippingTracker\Providers\SendboxShippingProvider::class,
        'cargoplug' => \Quitenoisemaker\ShippingTracker\Providers\CargoplugShippingProvider::class,
        // Later: Add real providers here
        // 'dhl' => \Quitenoisemaker\ShippingTracker\Providers\DhlShippingProvider::class,
        // 'gigl' => \Quitenoisemaker\ShippingTracker\Providers\GiglShippingProvider::class,
    ],

    'sendbox' => [
        'base_url' => env('SENDBOX_API_URL', 'https://live.sendbox.co'),
        'app_id' => env('SENDBOX_APP_ID'),
        'client_key' => env('SENDBOX_APP_CLIENT_KEY'),
        'access_token' => env('SENDBOX_ACCESS_TOKEN'),
        'required_webhook_fields' => [
            'events',
            'status',
        ],
    ],

    'cargoplug' => [    
        'base_url' => env('CARGOPLUG_API_URL', 'https://api.getcargoplug.com/api/v1'),
        'secret_key' => env('CARGOPLUG_SECRET_KEY'),
        'client_key' => env('CARGOPLUG_CLIENT_KEY'),
        'required_webhook_fields' => [
            'tracking_number',
            'status',
        ],
    ],

];
