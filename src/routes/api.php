<?php

use Illuminate\Support\Facades\Route;
use Quitenoisemaker\ShippingTracker\Http\Controllers\WebhookController;

Route::prefix('api/shipping')->group(function () {
    Route::post('webhooks/{provider}', [WebhookController::class, 'handle'])->name('shipping.webhook');
});
