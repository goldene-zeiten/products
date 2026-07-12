<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\PriceHistory;

use GoldeneZeiten\Products\Backend\StorageFolderResolver;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class PriceHistoryRecorder
{
    private const HISTORY_TABLE = 'tx_products_domain_model_pricehistoryentry';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly StorageFolderResolver $storageFolderResolver,
    ) {}

    /**
     * @param array<string, mixed> $incomingFieldArray
     */
    public function captureDirectPriceEdit(string $table, int|string $id, array $incomingFieldArray): void
    {
        if (!is_int($id) && !ctype_digit((string)$id)) {
            return; // NEW... placeholder: no prior price exists yet, nothing to record
        }
        $uid = (int)$id;
        $connection = $this->connectionPool->getConnectionForTable($table);
        $oldPrice = $connection->select(['price'], $table, ['uid' => $uid])->fetchOne();
        if ($oldPrice === false) {
            return; // record doesn't exist (defensive - shouldn't happen for an update)
        }
        $newPrice = (string)($incomingFieldArray['price'] ?? '');
        if ((string)$oldPrice === $newPrice) {
            return; // no actual change - don't pollute the ledger
        }

        $parentColumn = $table === 'tx_products_domain_model_article' ? 'article' : 'product';
        $this->insertHistoryRow($parentColumn, $uid, (string)$oldPrice, null, time());
    }

    /**
     * @param array<string, mixed> $effectiveRow
     */
    public function capturePeriodSave(array $effectiveRow): void
    {
        $parentColumn = (int)($effectiveRow['article'] ?? 0) > 0 ? 'article' : 'product';
        $parentUid = (int)($effectiveRow[$parentColumn] ?? 0);
        $price = (string)($effectiveRow['price'] ?? '0.00');
        $validFrom = (int)($effectiveRow['valid_from'] ?? 0) ?: null;
        $validUntil = (int)($effectiveRow['valid_until'] ?? 0) ?: null;
        $this->insertHistoryRow($parentColumn, $parentUid, $price, $validFrom, $validUntil);
    }

    private function insertHistoryRow(string $parentColumn, int $parentUid, string $price, ?int $validFrom, ?int $validUntil): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::HISTORY_TABLE);
        $now = time();
        $connection->insert(self::HISTORY_TABLE, [
            'pid' => $this->storageFolderResolver->resolve(),
            'tstamp' => $now,
            'crdate' => $now,
            $parentColumn => $parentUid,
            'price' => $price,
            'valid_from' => $validFrom ?? 0,
            'valid_until' => $validUntil ?? 0,
            'recorded_at' => $now,
        ]);
    }
}
