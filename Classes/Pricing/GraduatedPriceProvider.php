<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Pricing;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\PriceTier;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Quantity-based pricing: picks the highest `min_quantity` tier at or below the requested
 * quantity, preferring the article's own tiers over the product's; falls back to
 * {@see ProductPriceProvider} if none match.
 */
final class GraduatedPriceProvider implements PriceProviderInterface
{
    public function __construct(private readonly ProductPriceProvider $fallbackPriceProvider) {}

    public function getUnitPrice(Product $product, ?Article $article, int $quantity, ?ServerRequestInterface $request = null): Money
    {
        $tiers = $article !== null && $article->getPriceTiers()->count() > 0
            ? $article->getPriceTiers()
            : $product->getPriceTiers();
        $bestTier = $this->findBestTier($tiers, $quantity);
        if ($bestTier !== null) {
            return $bestTier->getPrice();
        }
        return $this->fallbackPriceProvider->getUnitPrice($product, $article, $quantity, $request);
    }

    /**
     * @param ObjectStorage<PriceTier> $tiers
     */
    private function findBestTier(ObjectStorage $tiers, int $quantity): ?PriceTier
    {
        $best = null;
        foreach ($tiers as $tier) {
            if ($tier->getMinQuantity() > $quantity) {
                continue;
            }
            if ($best === null || $tier->getMinQuantity() > $best->getMinQuantity()) {
                $best = $tier;
            }
        }
        return $best;
    }
}
