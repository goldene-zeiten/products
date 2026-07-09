<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Pricing;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The base price rule: an article's own price overrides the product's, unless it is the
 * `0.00 = inherit` sentinel.
 */
final class ProductPriceProvider implements PriceProviderInterface
{
    public function getUnitPrice(Product $product, ?Article $article, int $quantity, ?ServerRequestInterface $request = null): Money
    {
        if ($article !== null && $article->getPrice()->getCents() > 0) {
            return $article->getPrice();
        }
        return $product->getPrice();
    }
}
