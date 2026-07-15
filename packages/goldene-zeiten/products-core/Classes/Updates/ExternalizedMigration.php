<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Describes a migration that moved from the core into an optional add-on: where its legacy data lives and
 * which extension now owns it. Consumed by {@see AbstractExternalizedMigrationNoticeUpgradeWizard}.
 *
 * `legacyColumn` selects how "data is still present" is judged: null means the add-on owns its own legacy
 * table (present = the table holds rows); a column name means the data is a column on a shared table such
 * as tt_products (present = that column is populated on at least one row).
 */
#[Exclude]
final readonly class ExternalizedMigration
{
    public function __construct(
        public string $legacyTable,
        public string $extensionKey,
        public string $package,
        public string $feature,
        public ?string $legacyColumn = null,
    ) {}
}
