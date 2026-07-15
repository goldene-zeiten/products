<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Configuration;

/**
 * The DHL Express (MyDHL API) environment the calls go to. Sandbox is DHL's test environment.
 */
enum DhlExpressEnvironment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public static function fromSetting(string $value): self
    {
        return self::tryFrom($value) ?? self::Sandbox;
    }

    public function baseUrl(): string
    {
        return match ($this) {
            self::Sandbox => 'https://express.api.dhl.com/mydhlapi/test',
            self::Production => 'https://express.api.dhl.com/mydhlapi',
        };
    }
}
