<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

use Symfony\Component\Console\Output\OutputInterface;

final readonly class MediaMigrationContext
{
    public function __construct(
        public OutputInterface $output,
        public LegacyMediaFieldMapping $mapping,
        public int $legacyUid,
        public int $localUid,
    ) {}
}
