<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
// TODO: Migrate to TYPO3\CMS\Core\Attribute\UpgradeWizard once v13 support is dropped.
use TYPO3\CMS\Install\Attribute\UpgradeWizard;

/**
 * Tells an installation whose legacy visited-product counters are still present, but which upgraded the
 * core without the recently-viewed add-on, that the migration for that data now lives in
 * goldene-zeiten/products-recently-viewed.
 *
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only injects
 * dependencies into a public service.
 */
#[Autoconfigure(public: true)]
#[UpgradeWizard('products_recentlyViewedMigrationNotice')]
final class RecentlyViewedMigrationNoticeUpgradeWizard extends AbstractExternalizedMigrationNoticeUpgradeWizard
{
    protected function externalizedMigration(): ExternalizedMigration
    {
        return new ExternalizedMigration(
            legacyTable: 'sys_products_visited_products',
            extensionKey: 'products_recently_viewed',
            package: 'goldene-zeiten/products-recently-viewed',
            feature: 'recently-viewed and most-viewed product tracking',
        );
    }
}
