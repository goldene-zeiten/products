<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ShippingFallbackFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Shipping\SupplementaryShippingProviderInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Collecting in store, offered next to whatever carries the basket - it proves a supplementary provider
 * neither replaces the carriers nor keeps the manual fallback from filling in.
 */
final class CollectInStoreFixtureProvider implements SupplementaryShippingProviderInterface
{
    /**
     * The site the last quote was asked for, for tests proving the context carries it.
     */
    public static ?Site $lastQuotedSite = null;

    public function getIdentifier(): string
    {
        return 'collect';
    }

    public function getPriority(): int
    {
        return -10;
    }

    public function quote(ShippingContext $context): array
    {
        self::$lastQuotedSite = $context->getSite();
        return [new ShippingOption('collect', 'shop', 'Collect in store', Money::fromCents(0))];
    }

    public function resolve(string $optionIdentifier, ShippingContext $context): ?ShippingOption
    {
        return $optionIdentifier === 'shop' ? $this->quote($context)[0] : null;
    }
}
