<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Configuration;

/**
 * The Amazon Pay API region a merchant's account lives in. It selects the regional API host and is sent
 * as the `x-amz-pay-region` header; Amazon rejects a request signed for the wrong region.
 */
enum AmazonPayRegion: string
{
    case Eu = 'eu';
    case Na = 'na';
    case Jp = 'jp';

    public static function fromSetting(string $value): self
    {
        return match (strtolower($value)) {
            'na', 'us' => self::Na,
            'jp' => self::Jp,
            default => self::Eu,
        };
    }

    public function apiHost(): string
    {
        return match ($this) {
            self::Eu => 'pay-api.amazon.eu',
            self::Na => 'pay-api.amazon.com',
            self::Jp => 'pay-api.amazon.jp',
        };
    }
}
