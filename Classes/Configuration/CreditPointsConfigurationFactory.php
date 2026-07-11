<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Configuration;

use GoldeneZeiten\Products\Domain\Dto\CreditPointsEarningTier;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * `products.creditPoints.*` are Site Settings (Configuration/Sets/Products/settings.definitions.
 * yaml), read straight from the request's `site` attribute - see ProductsConfigurationFactory's
 * docblock for why ConfigurationManagerInterface can't be used for these.
 */
final class CreditPointsConfigurationFactory
{
    public function create(ServerRequestInterface $request): CreditPointsConfiguration
    {
        $site = $request->getAttribute('site');
        $settings = $site instanceof Site ? $site->getSettings() : null;

        return new CreditPointsConfiguration(
            (bool)($settings?->get('products.creditPoints.enabled', false) ?? false),
            Money::fromDecimalString((string)($settings?->get('products.creditPoints.moneyPerPoint', '0.10') ?? '0.10')),
            (string)($settings?->get('products.creditPoints.earningMode', 'perProduct') ?? 'perProduct'),
            $this->parseEarningTiers((array)($settings?->get('products.creditPoints.earningTiers', []) ?? [])),
            (float)($settings?->get('products.creditPoints.priceFactor', 0.0) ?? 0.0)
        );
    }

    /**
     * @param array<int, mixed> $rawTiers
     * @return CreditPointsEarningTier[]
     */
    private function parseEarningTiers(array $rawTiers): array
    {
        $tiers = [];
        foreach ($rawTiers as $entry) {
            $parts = explode(':', (string)$entry, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $tiers[] = new CreditPointsEarningTier(Money::fromDecimalString(trim($parts[0])), (int)trim($parts[1]));
        }
        return $tiers;
    }
}
