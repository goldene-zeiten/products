<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Pricing;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Applies a shopper's personal or FE-usergroup discount (see FrontendUserResolver::
 * getDiscountPercent()) as the final step on top of `GraduatedPriceProvider`'s quantity-based
 * price - without a request (no frontend context) no discount can be resolved, so the
 * undiscounted price passes through unchanged.
 */
final class UserGroupDiscountPriceProvider implements PriceProviderInterface
{
    public function __construct(
        private readonly GraduatedPriceProvider $graduatedPriceProvider,
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    public function getUnitPrice(Product $product, ?Article $article, int $quantity, ?ServerRequestInterface $request = null): Money
    {
        $price = $this->graduatedPriceProvider->getUnitPrice($product, $article, $quantity, $request);
        if ($request === null) {
            return $price;
        }

        $discountPercent = $this->frontendUserResolver->getDiscountPercent($request);
        if ($discountPercent <= 0.0) {
            return $price;
        }

        return $price->multiply(1 - $discountPercent / 100);
    }
}
