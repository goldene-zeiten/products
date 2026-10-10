<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Pricing;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Applies whichever is higher of the category discount ({@see CategoryDiscountResolver}) or the
 * shopper's personal discount ({@see FrontendUserResolver::getDiscountPercent()}); never stacked.
 *
 * Several price providers implement the interface; this is the one anything asking for a
 * {@see PriceProviderInterface} gets, so it is the shop's effective pricing rule.
 */
#[AsAlias(PriceProviderInterface::class)]
final class CategoryDiscountPriceProvider implements PriceProviderInterface
{
    private const DEFAULT_MODE = 'maxAcrossTree';

    public function __construct(
        private readonly GraduatedPriceProvider $graduatedPriceProvider,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly CategoryDiscountResolver $categoryDiscountResolver
    ) {}

    public function getUnitPrice(Product $product, ?Article $article, int $quantity, ?ServerRequestInterface $request = null): Money
    {
        $price = $this->graduatedPriceProvider->getUnitPrice($product, $article, $quantity, $request);

        $discountPercent = max(
            $this->categoryDiscountResolver->getDiscountPercent($product, $this->discountFieldMode($request)),
            $request !== null ? $this->frontendUserResolver->getDiscountPercent($request) : 0.0
        );

        return $price->discountByPercent($discountPercent);
    }

    private function discountFieldMode(?ServerRequestInterface $request): string
    {
        $mode = $request?->getAttribute('site')?->getSettings()->get('products.pricing.discountFieldMode', self::DEFAULT_MODE);
        return is_string($mode) && $mode !== '' ? $mode : self::DEFAULT_MODE;
    }
}
