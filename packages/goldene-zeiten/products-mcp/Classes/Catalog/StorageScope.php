<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Catalog;

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * Limits an Extbase query to the storage folder of the site ("products.pids.storageFolder"), as the
 * frontend does through its TypoScript, which an MCP request does not have.
 */
final readonly class StorageScope
{
    /**
     * @template T of object
     * @param QueryInterface<T> $query
     */
    public function apply(QueryInterface $query, Site $site): void
    {
        $storagePageIds = $this->storagePageIds($site);
        $settings = $query->getQuerySettings();
        $settings->setRespectStoragePage($storagePageIds !== []);
        if ($storagePageIds !== []) {
            $settings->setStoragePageIds($storagePageIds);
        }
    }

    /**
     * @return int[] empty while the site names no storage folder
     */
    public function storagePageIds(Site $site): array
    {
        $storageFolder = (int)$site->getSettings()->get('products.pids.storageFolder', 0);

        return $storageFolder > 0 ? [$storageFolder] : [];
    }
}
