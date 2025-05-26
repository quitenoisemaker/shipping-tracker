<?php

namespace Quitenoisemaker\ShippingTracker\Contracts;

/**
 * Interface for all shipping provider integrations.
 */
interface ShippingProviderInterface
{
    /**
     * Determine whether this provider supports the given tracking number.
     *
     * Useful when dynamically selecting a provider based on prefix or format.
     *
     * @param string $trackingNumber
     * @return bool
     */
    public function supports(string $trackingNumber): bool;

    /**
     * Fetch the latest tracking information for a shipment.
     *
     * @param string $trackingNumber
     * @return array{
     *     tracking_number: string,
     *     status: string,
     *     location?: string,
     *     estimated_delivery?: string,
     *     raw?: mixed
     * }
     */
    public function track(string $trackingNumber): mixed;

    /**
     * Handle webhook callback from the shipping provider.
     *
     * This is usually called by a webhook route inside your Laravel app.
     *
     * @param array $payload
     * @return void
     */
    public function handleWebhook(array $payload): void;
}
