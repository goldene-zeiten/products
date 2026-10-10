<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\PriceHistory;

use GoldeneZeiten\Products\Core\Backend\StorageFolderResolver;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class PriceHistoryRecorder
{
    private const HISTORY_TABLE = 'tx_products_domain_model_pricehistoryentry';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly StorageFolderResolver $storageFolderResolver,
    ) {}

    /**
     * Records the price a product or article had before a direct price edit was written.
     */
    public function captureDirectPriceEdit(string $table, int $uid, string $previousPrice): void
    {
        $parentColumn = $table === 'tx_products_domain_model_article' ? 'article' : 'product';
        $this->insertHistoryRow($parentColumn, $uid, $previousPrice, null, time());
    }

    /**
     * Records a saved public price period, given its row as stored in the database.
     *
     * @param array<string, mixed> $periodRow
     */
    public function capturePeriodSave(array $periodRow): void
    {
        $parentColumn = (int)($periodRow['article'] ?? 0) > 0 ? 'article' : 'product';
        $parentUid = (int)($periodRow[$parentColumn] ?? 0);
        $price = (string)($periodRow['price'] ?? '0.00');
        $validFrom = (int)($periodRow['valid_from'] ?? 0) ?: null;
        $validUntil = (int)($periodRow['valid_until'] ?? 0) ?: null;
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
