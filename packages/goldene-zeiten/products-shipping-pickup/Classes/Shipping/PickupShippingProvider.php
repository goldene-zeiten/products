<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Pickup\Shipping;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Shipping\SupplementaryShippingProviderInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Pick-up in store: every branch is a shipping option of its own, offered next to the shop's carriers, so
 * choosing where to collect the order is choosing its shipping. The option label carries the branch address, so the order and its mails say where
 * the order waits.
 */
final class PickupShippingProvider implements SupplementaryShippingProviderInterface
{
    public const IDENTIFIER = 'pickup';
    private const TABLE = 'tx_productsshippingpickup_domain_model_branch';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LanguageServiceFactory $languageServiceFactory
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    /**
     * Below the carriers, so a delivery stays the preselected option.
     */
    public function getPriority(): int
    {
        return -10;
    }

    public function quote(ShippingContext $context): array
    {
        $options = [];
        foreach ($this->branches(null) as $branch) {
            $options[] = $this->option($branch, $context);
        }
        return $options;
    }

    public function resolve(string $optionIdentifier, ShippingContext $context): ?ShippingOption
    {
        $branch = $this->branches((int)$optionIdentifier)[0] ?? null;
        return $branch !== null && (string)$branch['uid'] === $optionIdentifier ? $this->option($branch, $context) : null;
    }

    /**
     * @param array<string, mixed> $branch
     */
    private function option(array $branch, ShippingContext $context): ShippingOption
    {
        $site = $context->getSite();
        $languageService = $this->languageServiceFactory->create($site instanceof Site ? (string)$site->getDefaultLanguage()->getLocale() : 'default');
        $address = implode(', ', array_filter([
            (string)$branch['street'],
            trim($branch['zip'] . ' ' . $branch['city']),
        ]));
        $label = sprintf(
            $languageService->sL('LLL:EXT:products_shipping_pickup/Resources/Private/Language/locallang.xlf:option.label'),
            (string)$branch['title']
        );
        return new ShippingOption(
            self::IDENTIFIER,
            (string)$branch['uid'],
            $address !== '' ? $label . ' (' . $address . ')' : $label,
            Money::fromCents(0),
            null,
            (string)$branch['opening_hours']
        );
    }

    /**
     * @return array<int, array<string, mixed>> visible branches in their backend order, or the one asked for
     */
    private function branches(?int $uid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->select('uid', 'title', 'street', 'zip', 'city', 'opening_hours')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('sorting');
        if ($uid !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)));
        }
        $result = $queryBuilder->executeQuery();
        $branches = [];
        while ($row = $result->fetchAssociative()) {
            $branches[] = $row;
        }
        return $branches;
    }
}
