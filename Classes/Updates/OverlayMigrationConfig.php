<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

final readonly class OverlayMigrationConfig
{
    public function __construct(
        public string $legacyLanguageTable,
        public string $parentField,
        public string $legacyParentTable,
        public string $localTable,
    ) {}
}
