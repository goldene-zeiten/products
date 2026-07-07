<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

/**
 * Table/field wiring for one `LegacyMediaMigrator::migrateRow()` call. `legacyField` is the plain
 * legacy column holding a comma-separated filename list (e.g. `image`); the sibling FAL usage
 * counter column is always `<legacyField>_uid`, following the `tt_products` convention.
 */
final readonly class LegacyMediaFieldMapping
{
    public function __construct(
        public string $legacyTable,
        public string $legacyField,
        public string $localTable,
        public string $localField,
    ) {}
}
