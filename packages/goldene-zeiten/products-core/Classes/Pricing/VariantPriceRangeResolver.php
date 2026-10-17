<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Pricing;

use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\VariantPriceRange;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The price range of a product's variants at the prices the basket charges, for the detail page before a
 * variant is chosen.
 */
final class VariantPriceRangeResolver
{
    public function __construct(
        private readonly PriceProviderInterface $priceProvider
    ) {}

    /**
     * @return VariantPriceRange|null null for a product without variants
     */
    public function resolve(Product $product, ?ServerRequestInterface $request): ?VariantPriceRange
    {
        $prices = [];
        $anyInStock = false;
        foreach ($product->getArticles() as $article) {
            $prices[] = $this->priceProvider->getUnitPrice($product, $article, 1, $request);
            $anyInStock = $anyInStock || $article->isUnlimitedStock() || $article->getInStock() > 0;
        }
        if ($prices === []) {
            return null;
        }
        usort($prices, static fn(Money $left, Money $right): int => $left->getCents() <=> $right->getCents());
        return new VariantPriceRange($prices[0], $prices[array_key_last($prices)], count($prices), $anyInStock);
    }
}
