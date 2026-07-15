<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

final readonly class LegacyMediaFieldMapping
{
    public function __construct(
        public string $legacyTable,
        public string $legacyField,
        public string $localTable,
        public string $localField,
    ) {}
}
