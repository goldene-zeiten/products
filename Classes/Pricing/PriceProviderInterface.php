<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Pricing;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;

/**
 * Resolves the unit price a basket line should use. Bound to `GraduatedPriceProvider` by default
 * (see Services.yaml); a plain `ProductPriceProvider` instance is used as its fallback rather than
 * as an alternative binding, so quantity-based pricing is always considered first.
 */
interface PriceProviderInterface
{
    public function getUnitPrice(Product $product, ?Article $article, int $quantity): Money;
}
