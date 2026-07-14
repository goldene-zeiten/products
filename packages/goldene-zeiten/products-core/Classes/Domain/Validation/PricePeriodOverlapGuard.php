<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Validation;

use GoldeneZeiten\Products\Core\Exception\PricePeriodOverlapException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class PricePeriodOverlapGuard
{
    private const TABLE = 'tx_products_domain_model_priceperiod';

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @param array<string, mixed> $incomingFieldArray
     * @return array<string, mixed> the effective (existing + incoming merged) row, for the caller to inspect (e.g. fe_group)
     */
    public function assertNoOverlap(array $incomingFieldArray, int|string $id): array
    {
        $existingRow = [];
        if (is_int($id) || ctype_digit((string)$id)) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
            $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            $existingRow = $queryBuilder->select('product', 'article', 'fe_group', 'valid_from', 'valid_until')
                ->from(self::TABLE)
                ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int)$id, Connection::PARAM_INT)))
                ->executeQuery()
                ->fetchAssociative() ?: [];
        }

        $effectiveRow = array_merge(
            ['product' => 0, 'article' => 0, 'fe_group' => 0, 'valid_from' => 0, 'valid_until' => 0],
            $existingRow,
            $incomingFieldArray
        );

        $parentColumn = (int)($effectiveRow['article'] ?? 0) > 0 ? 'article' : 'product';
        $parentUid = (int)($effectiveRow[$parentColumn] ?? 0);
        $feGroup = (int)($effectiveRow['fe_group'] ?? 0);
        $newFrom = (int)($effectiveRow['valid_from'] ?? 0);
        $newUntil = (int)($effectiveRow['valid_until'] ?? 0) ?: PHP_INT_MAX;

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $siblings = $queryBuilder->select('uid', 'valid_from', 'valid_until')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq($parentColumn, $queryBuilder->createNamedParameter($parentUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('fe_group', $queryBuilder->createNamedParameter($feGroup, Connection::PARAM_INT)),
                $queryBuilder->expr()->neq('uid', $queryBuilder->createNamedParameter(is_int($id) ? $id : 0, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($siblings as $sibling) {
            $siblingFrom = (int)$sibling['valid_from'];
            $siblingUntil = (int)$sibling['valid_until'] ?: PHP_INT_MAX;
            $overlaps = $newFrom < $siblingUntil && $siblingFrom < $newUntil;
            if ($overlaps) {
                throw new PricePeriodOverlapException(
                    sprintf('Price period for %s:%d overlaps an existing period (uid %d) in the same scope (fe_group %d).', $parentColumn, $parentUid, (int)$sibling['uid'], $feGroup),
                    1751900000
                );
            }
        }

        return $effectiveRow;
    }
}
