<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend;

use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Resolves the storage folder pid from site settings.
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
