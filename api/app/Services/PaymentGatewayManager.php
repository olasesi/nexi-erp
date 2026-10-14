<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Integration;
use RuntimeException;

/**
 * Resolves the payment gateway for a company. A company that has connected one
 * of the checkout-capable providers (see `payments.checkout_providers`) uses
 * that gateway; otherwise the configured default driver (the offline stub) is
 * returned so the pay surface never breaks.
 */
class PaymentGatewayManager
{
    public function __construct(
        protected BusinessSettingsService $settings
    ) {}

    public function driver(?int $companyId): PaymentGateway
    {
        return $this->make($this->activeProvider($companyId), $companyId);
    }

    /**
     * Provider key of the gateway active for a company.
     */
    public function activeProvider(?int $companyId): string
    {
        $default = (string) config('payments.driver', 'stub');
        $providers = array_values(array_filter((array) config('payments.checkout_providers', [])));

        if ($companyId === null || $providers === []) {
            return $default;
        }

        $connected = Integration::query()
            ->withoutGlobalScope('company_scope')
            ->where('company_id', $companyId)
            ->where('is_connected', true)
            ->whereIn('provider', $providers)
            ->orderByDesc('connected_at')
            ->value('provider');

        return is_string($connected) && $connected !== '' ? $connected : $default;
    }

    /**
     * Build a driver for an explicit provider, loading its per-company
     * credentials from the matching business-settings group.
     */
    public function make(string $provider, ?int $companyId): PaymentGateway
    {
        $class = $this->driverClass($provider);

        if ($class === null) {
            throw new RuntimeException("Payment gateway [{$provider}] is not registered.");
        }

        $credentials = $this->settings->group($companyId, $provider);

        /** @var PaymentGateway $gateway */
        $gateway = new $class($credentials);

        return $gateway;
    }

    /**
     * Whether a registered driver exists for the provider (used to reject
     * webhooks aimed at unknown providers).
     */
    public function supports(string $provider): bool
    {
        return $this->driverClass($provider) !== null;
    }

    private function driverClass(string $provider): ?string
    {
        $class = config("payments.drivers.{$provider}");

        return is_string($class) && class_exists($class) ? $class : null;
    }
}
