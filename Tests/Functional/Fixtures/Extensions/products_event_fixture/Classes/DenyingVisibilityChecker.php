<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventFixture;

use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Visibility\ProductVisibilityInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Fixture visibility checker that can be configured to deny access to a specific product.
 * Used in tests to verify the deny-wins-over-allow aggregation rule.
 */
final class DenyingVisibilityChecker implements ProductVisibilityInterface
{
    public static bool $enabled = false;

    public static int $deniedProductUid = 0;

    public function isVisible(Product $product, ServerRequestInterface $request): bool
    {
        if (!self::$enabled) {
            return true;
        }
        return $product->getUid() !== self::$deniedProductUid;
    }
}
