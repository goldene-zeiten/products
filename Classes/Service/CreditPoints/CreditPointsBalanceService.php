<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\CreditPoints;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Maintains a running per-user balance in a dedicated table, atomically kept in sync with the
 * tx_products_domain_model_creditpointstransaction ledger (which stays the audit trail, unchanged
 * - every earn/redeem is still recorded there). Unlike summing the ledger on demand, a maintained
 * running total lets earn/redeem be guarded by a single atomic SQL statement (mirroring
 * StockService's stock decrement), so two concurrent redemptions can never both succeed against a
 * balance only one of them could actually afford.
 *
 * ensureRowExists() lazily adopts the ledger's current sum as the row's starting balance the first
 * time it's touched for a given user - this is what keeps pre-existing ledger data (including
 * historical or manually-inserted adjustments that never went through credit()/debitIfAffordable())
 * correctly reflected, rather than silently starting every unseen user at zero.
 */
final class CreditPointsBalanceService
{
    private const BALANCE_TABLE = 'tx_products_domain_model_creditpointsbalance';
    private const LEDGER_TABLE = 'tx_products_domain_model_creditpointstransaction';

    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {}

    public function getBalance(int $frontendUser): int
    {
        if ($frontendUser === 0) {
            return 0;
        }
        $this->ensureRowExists($frontendUser);

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::BALANCE_TABLE);
        $balance = $queryBuilder
            ->select('balance')
            ->from(self::BALANCE_TABLE)
            ->where($queryBuilder->expr()->eq('frontend_user', $queryBuilder->createNamedParameter($frontendUser, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();

        return $balance === false ? 0 : (int)$balance;
    }

    /**
     * Adds earned points - always succeeds (earning never needs a guard).
     */
    public function credit(int $frontendUser, int $points): void
    {
        $this->ensureRowExists($frontendUser);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::BALANCE_TABLE);
        $queryBuilder->update(self::BALANCE_TABLE)
            ->set('balance', $queryBuilder->quoteIdentifier('balance') . ' + ' . $queryBuilder->createNamedParameter($points, Connection::PARAM_INT), false)
            ->where($queryBuilder->expr()->eq('frontend_user', $queryBuilder->createNamedParameter($frontendUser, Connection::PARAM_INT)))
            ->executeStatement();
    }

    /**
     * Atomically subtracts points, but only if the balance can still afford it - the check and the
     * write happen in one SQL statement, so two concurrent spends against the same balance cannot
     * both succeed.
     *
     * @return bool false if the balance could not afford this many points
     */
    public function debitIfAffordable(int $frontendUser, int $points): bool
    {
        $this->ensureRowExists($frontendUser);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::BALANCE_TABLE);
        $affectedRows = $queryBuilder->update(self::BALANCE_TABLE)
            ->set('balance', $queryBuilder->quoteIdentifier('balance') . ' - ' . $queryBuilder->createNamedParameter($points, Connection::PARAM_INT), false)
            ->where(
                $queryBuilder->expr()->eq('frontend_user', $queryBuilder->createNamedParameter($frontendUser, Connection::PARAM_INT)),
                $queryBuilder->expr()->gte('balance', $queryBuilder->createNamedParameter($points, Connection::PARAM_INT))
            )
            ->executeStatement();

        return $affectedRows > 0;
    }

    /**
     * Idempotent get-or-adopt: a concurrent double-insert for a brand-new user's first-ever
     * transaction is harmless - the loser's insert fails the primary key constraint and is simply
     * ignored, since the row it wanted to create already exists (created by the winner).
     */
    private function ensureRowExists(int $frontendUser): void
    {
        $selectQueryBuilder = $this->connectionPool->getQueryBuilderForTable(self::BALANCE_TABLE);
        $exists = $selectQueryBuilder
            ->count('frontend_user')
            ->from(self::BALANCE_TABLE)
            ->where($selectQueryBuilder->expr()->eq('frontend_user', $selectQueryBuilder->createNamedParameter($frontendUser, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();

        if ((int)$exists > 0) {
            return;
        }

        $ledgerQueryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LEDGER_TABLE);
        $ledgerSum = $ledgerQueryBuilder
            ->selectLiteral('SUM(' . $ledgerQueryBuilder->quoteIdentifier('points') . ') AS balance')
            ->from(self::LEDGER_TABLE)
            ->where($ledgerQueryBuilder->expr()->eq('frontend_user', $ledgerQueryBuilder->createNamedParameter($frontendUser, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();

        try {
            $this->connectionPool->getConnectionForTable(self::BALANCE_TABLE)->insert(self::BALANCE_TABLE, [
                'frontend_user' => $frontendUser,
                'balance' => (int)($ledgerSum ?? 0),
            ]);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            // Another concurrent request already created (and correctly initialized) the row.
        }
    }
}
