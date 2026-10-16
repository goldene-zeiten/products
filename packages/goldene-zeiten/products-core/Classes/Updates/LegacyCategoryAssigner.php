<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Assigns a migrated product to a category, once, keeping the product's category counter in step.
 */
final class LegacyCategoryAssigner
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    public function isAssigned(int $productUid, int $categoryUid): bool
    {
        return $this->connectionPool->getConnectionForTable('tx_products_product_category_mm')->count('*', 'tx_products_product_category_mm', [
            'uid_local' => $productUid,
            'uid_foreign' => $categoryUid,
        ]) > 0;
    }

    public function assign(int $productUid, int $categoryUid): void
    {
        if ($this->isAssigned($productUid, $categoryUid)) {
            return;
        }
        $mm = $this->connectionPool->getConnectionForTable('tx_products_product_category_mm');
        $assigned = $mm->count('*', 'tx_products_product_category_mm', ['uid_local' => $productUid]);
        $mm->insert('tx_products_product_category_mm', [
            'uid_local' => $productUid,
            'uid_foreign' => $categoryUid,
            'sorting' => $assigned + 1,
        ]);
        $this->connectionPool->getConnectionForTable('tx_products_domain_model_product')->update(
            'tx_products_domain_model_product',
            ['categories' => $assigned + 1],
            ['uid' => $productUid]
        );
    }
}
