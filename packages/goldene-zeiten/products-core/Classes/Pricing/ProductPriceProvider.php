<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Pricing;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Psr\Http\Message\ServerRequestInterface;

/**
 * An article's own price overrides the product's (`0.00` means "inherit"), or in "surcharge"
 * mode is added on top instead.
 */
final class ProductPriceProvider implements PriceProviderInterface
{
    public function getUnitPrice(Product $product, ?Article $article, int $quantity, ?ServerRequestInterface $request = null): Money
    {
        if ($article === null || $article->getPrice()->getCents() === 0) {
            return $product->getPrice();
        }
        if ($article->getPriceMode() === 'surcharge') {
            return $product->getPrice()->add($article->getPrice());
        }
        return $article->getPrice();
    }
}
