<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\RateLimit;

/**
 * The paths of the MCP package that are throttled, each counted on its own: the endpoint assistants talk to and
 * the checkout link customers open.
 */
enum RateLimitScope: string
{
    case Endpoint = 'products-mcp-endpoint';
    case Checkout = 'products-mcp-checkout';

    /**
     * The site setting that holds the allowed requests per minute; 0 switches the limit off.
     */
    public function settingKey(): string
    {
        return match ($this) {
            self::Endpoint => 'products.mcp.rateLimit',
            self::Checkout => 'products.mcp.checkoutRateLimit',
        };
    }

    public function defaultLimit(): int
    {
        return match ($this) {
            self::Endpoint => 120,
            self::Checkout => 30,
        };
    }
}
