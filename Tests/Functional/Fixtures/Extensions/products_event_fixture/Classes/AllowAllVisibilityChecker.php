<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventFixture;

use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Visibility\ProductVisibilityInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Fixture visibility checker that always allows all products.
 * Used to verify that deny-wins-over-allow logic: DenyingVisibilityChecker can still deny despite this allowing.
 */
final class AllowAllVisibilityChecker implements ProductVisibilityInterface
{
    public function isVisible(Product $product, ServerRequestInterface $request): bool
    {
        return true;
    }
}
