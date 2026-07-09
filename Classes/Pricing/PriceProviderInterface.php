<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Pricing;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the unit price a basket line should use. Bound to `CategoryDiscountPriceProvider` by
 * default (see Services.yaml), which decorates `GraduatedPriceProvider` as its own fallback rather
 * than as an alternative binding, so quantity-based pricing is always considered before either the
 * category-cascading or per-shopper discount is applied on top.
 */
interface PriceProviderInterface
{
    public function getUnitPrice(Product $product, ?Article $article, int $quantity, ?ServerRequestInterface $request = null): Money;
}
