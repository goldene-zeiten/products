<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Configuration;

/**
 * The PayPal environment the API calls go to. Sandbox is PayPal's developer test environment; live
 * money only moves in production.
 */
enum PaypalEnvironment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public static function fromSetting(string $value): self
    {
        return self::tryFrom($value) ?? self::Sandbox;
    }

    /**
     * Base URL for the OAuth token endpoint and the Orders/Payments REST API in this environment.
     */
    public function baseUrl(): string
    {
        return match ($this) {
            self::Sandbox => 'https://api-m.sandbox.paypal.com',
            self::Production => 'https://api-m.paypal.com',
        };
    }
}
