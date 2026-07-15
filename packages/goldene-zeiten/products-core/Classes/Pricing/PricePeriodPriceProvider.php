<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Pricing;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\PricePeriod;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Resolves the currently-active time-boxed price period for a product/article, if any, respecting
 * public vs. fe_group-scoped (reseller) audience and the Site-Set-configurable precedence policy
 * when both a public and a reseller period are active concurrently. Returns null when no period
 * currently applies, so {@see GraduatedPriceProvider} can combine this with a quantity-tier price.
 */
final class PricePeriodPriceProvider
{
    private const POLICY_LOWEST_WINS = 'lowestWins';
    private const POLICY_RESELLER_FIXED = 'resellerFixed';

    public function __construct(private readonly FrontendUserResolver $frontendUserResolver) {}

    public function findActivePeriodPrice(Product $product, ?Article $article, ?ServerRequestInterface $request): ?Money
    {
        $periods = ($article !== null && $article->getPricePeriods()->count() > 0)
            ? $article->getPricePeriods()
            : $product->getPricePeriods();
        if ($periods->count() === 0) {
            return null;
        }

        $now = time();
        $publicPeriod = $this->findMatching($periods, $now, null);
        $feGroupUids = $request !== null ? $this->frontendUserResolver->getGroupUids($request) : [];
        $resellerPeriod = $feGroupUids !== [] ? $this->findMatching($periods, $now, $feGroupUids) : null;

        if ($resellerPeriod === null) {
            return $publicPeriod?->getPrice();
        }
        if ($publicPeriod === null) {
            return $resellerPeriod->getPrice();
        }

        $policy = (string)($request->getAttribute('site')?->getSettings()->get('products.pricing.resellerPeriodPrecedence', self::POLICY_LOWEST_WINS) ?? self::POLICY_LOWEST_WINS);
        return $policy === self::POLICY_RESELLER_FIXED
            ? $resellerPeriod->getPrice()
            : Money::fromCents(min($resellerPeriod->getPrice()->getCents(), $publicPeriod->getPrice()->getCents()));
    }

    /**
     * @param ObjectStorage<PricePeriod> $periods
     * @param int[]|null $feGroupUids null = look for the public (fe_group 0) period
     */
    private function findMatching(ObjectStorage $periods, int $now, ?array $feGroupUids): ?PricePeriod
    {
        foreach ($periods as $period) {
            $matchesScope = $feGroupUids === null ? $period->isPublic() : in_array($period->getFeGroup(), $feGroupUids, true);
            if (!$matchesScope) {
                continue;
            }
            $from = $period->getValidFrom()?->getTimestamp() ?? 0;
            $until = $period->getValidUntil()?->getTimestamp() ?: PHP_INT_MAX;
            if ($now >= $from && $now < $until) {
                return $period; // non-overlap is DataHandler-enforced per scope, so at most one match is expected
            }
        }
        return null;
    }
}
