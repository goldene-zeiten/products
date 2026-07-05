<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Backend;

use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Resolves the page uid new category/product/article records should be created in.
 * Read from the first configured site's `products.pids.storageFolder` setting, the same
 * setting the frontend plugins use to find the shop's records.
 */
final class StorageFolderResolver
{
    public function __construct(private readonly SiteFinder $siteFinder) {}

    public function resolve(): int
    {
        foreach ($this->siteFinder->getAllSites() as $site) {
            $pid = (int)$site->getSettings()->get('products.pids.storageFolder', 0);
            if ($pid > 0) {
                return $pid;
            }
        }
        return 0;
    }
}
