<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Pricing;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\PriceTier;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Quantity-based pricing: picks the highest `min_quantity` tier at or below the requested quantity, or an
 * active price period, whichever is lower; falls back to {@see ProductPriceProvider} if neither applies.
 *
 * An article's own tiers and periods always apply to it. The product's tiers and periods apply only to an
 * article that takes its price from the product - one without an own price, or one in surcharge mode, which
 * then adds its surcharge on top. A tier without a price is not a price and is ignored.
 */
final class GraduatedPriceProvider implements PriceProviderInterface
{
    public function __construct(
        private readonly ProductPriceProvider $fallbackPriceProvider,
        private readonly PricePeriodPriceProvider $pricePeriodPriceProvider,
    ) {}

    public function getUnitPrice(Product $product, ?Article $article, int $quantity, ?ServerRequestInterface $request = null): Money
    {
        return $this->resolve($product, $article, $quantity, $request, true);
    }

    /**
     * The list price for the quantity: quantity tiers apply, a price period - a time-boxed discount - does not.
     */
    public function getQuantityPrice(Product $product, ?Article $article, int $quantity): Money
    {
        return $this->resolve($product, $article, $quantity, null, false);
    }

    private function resolve(Product $product, ?Article $article, int $quantity, ?ServerRequestInterface $request, bool $withPeriods): Money
    {
        $ownTiers = $article !== null && $article->getPriceTiers()->count() > 0;
        $ownPeriods = $article !== null && $article->getPricePeriods()->count() > 0;
        $inheritsProductPricing = $this->inheritsProductPricing($article);
        $candidates = [];
        if ($ownTiers || $inheritsProductPricing) {
            $tierPrice = $this->findBestTier($ownTiers ? $article->getPriceTiers() : $product->getPriceTiers(), $quantity)?->getPrice();
            $candidates[] = $tierPrice !== null ? $this->withSurcharge($tierPrice, $article, !$ownTiers) : null;
        }
        if ($withPeriods && ($ownPeriods || $inheritsProductPricing)) {
            $periodPrice = $this->pricePeriodPriceProvider->findActivePeriodPrice($product, $article, $request);
            $candidates[] = $periodPrice !== null ? $this->withSurcharge($periodPrice, $article, !$ownPeriods) : null;
        }
        $candidates = array_filter($candidates);
        if ($candidates === []) {
            return $this->fallbackPriceProvider->getUnitPrice($product, $article, $quantity, $request);
        }
        return Money::fromCents(min(array_map(static fn(Money $m): int => $m->getCents(), $candidates)));
    }

    /**
     * A surcharge article adds its surcharge to a price the product sets, not to one of its own.
     */
    private function withSurcharge(Money $price, ?Article $article, bool $priceIsFromProduct): Money
    {
        return $priceIsFromProduct && $article?->getPriceMode() === 'surcharge' ? $price->add($article->getPrice()) : $price;
    }

    private function inheritsProductPricing(?Article $article): bool
    {
        return $article === null || $article->getPrice()->getCents() === 0 || $article->getPriceMode() === 'surcharge';
    }

    /**
     * @param ObjectStorage<PriceTier> $tiers
     */
    private function findBestTier(ObjectStorage $tiers, int $quantity): ?PriceTier
    {
        $best = null;
        foreach ($tiers as $tier) {
            if ($tier->getMinQuantity() > $quantity || $tier->getPrice()->getCents() <= 0) {
                continue;
            }
            if ($best === null || $tier->getMinQuantity() > $best->getMinQuantity()) {
                $best = $tier;
            }
        }
        return $best;
    }
}
