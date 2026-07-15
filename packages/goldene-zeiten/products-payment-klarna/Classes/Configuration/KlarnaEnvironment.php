<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Configuration;

/**
 * The Klarna environment the API calls go to. Playground is Klarna's test environment; live money only
 * moves in production. These are the European (EU) hosts - other regions (NA/OC) would be a later addition.
 */
enum KlarnaEnvironment: string
{
    case Playground = 'playground';
    case Production = 'production';

    public static function fromSetting(string $value): self
    {
        return self::tryFrom($value) ?? self::Playground;
    }

    public function baseUrl(): string
    {
        return match ($this) {
            self::Playground => 'https://api.playground.klarna.com',
            self::Production => 'https://api.klarna.com',
        };
    }
}
