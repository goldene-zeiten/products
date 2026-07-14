<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\PriceHistory;

use Doctrine\DBAL\ParameterType;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class PriceHistoryLookbackService
{
    private const HISTORY_TABLE = 'tx_products_domain_model_pricehistoryentry';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Find the lowest price recorded for a product or article within the given lookback window.
     * Returns null if no matching history is found or if the minimum price is >= currentPrice.
     */
    public function findLowestPriceSince(?int $productUid, ?int $articleUid, int $sinceTimestamp): ?Money
    {
        if (($productUid === null || $productUid === 0) && ($articleUid === null || $articleUid === 0)) {
            return null;
        }

        $now = time();

        // Prefer article if both are given and article uid is non-zero
        $parentColumn = ($articleUid !== null && $articleUid > 0) ? 'article' : 'product';
        $parentUid = $parentColumn === 'article' ? $articleUid : $productUid;

        if ($parentUid === null || $parentUid === 0) {
            return null;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::HISTORY_TABLE);
        $result = $queryBuilder
            ->select('price')
            ->from(self::HISTORY_TABLE)
            ->where(
                $queryBuilder->expr()->eq($parentColumn, $queryBuilder->createNamedParameter($parentUid, ParameterType::INTEGER))
            )
            ->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('valid_from', 0),
                    $queryBuilder->expr()->lt('valid_from', $queryBuilder->createNamedParameter($now, ParameterType::INTEGER))
                )
            )
            ->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('valid_until', 0),
                    $queryBuilder->expr()->gt('valid_until', $queryBuilder->createNamedParameter($sinceTimestamp, ParameterType::INTEGER))
                )
            )
            ->orderBy('price', 'ASC')
            ->setMaxResults(1)
            ->executeQuery();

        $row = $result->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return Money::fromDecimalString((string)$row['price']);
    }
}
