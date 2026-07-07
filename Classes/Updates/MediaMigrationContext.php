<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Per-row context threaded through `LegacyMediaMigrator`'s private helpers, keeping their
 * parameter counts within the project's method-design limit.
 */
final readonly class MediaMigrationContext
{
    public function __construct(
        public OutputInterface $output,
        public LegacyMediaFieldMapping $mapping,
        public int $legacyUid,
        public int $localUid,
    ) {}
}
