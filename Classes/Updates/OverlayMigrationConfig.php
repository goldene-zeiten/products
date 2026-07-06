<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

/**
 * Table/field wiring for one `LegacyOverlayMigrator::migrate()` call.
 */
final readonly class OverlayMigrationConfig
{
    public function __construct(
        public string $legacyLanguageTable,
        public string $parentField,
        public string $legacyParentTable,
        public string $localTable,
    ) {}
}
