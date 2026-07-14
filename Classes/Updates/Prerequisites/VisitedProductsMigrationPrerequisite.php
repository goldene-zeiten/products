<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates\Prerequisites;

use GoldeneZeiten\Products\Updates\LegacyMigrationHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\PrerequisiteInterface;

/**
 * Blocks dependent wizards until both legacy visited-products tables are fully migrated.
 *
 * Public for use as an upgrade wizard prerequisite.
 */
#[Autoconfigure(public: true)]
final class VisitedProductsMigrationPrerequisite implements PrerequisiteInterface, ChattyInterface
{
    private const LEGACY_GLOBAL_TABLE = 'sys_products_visited_products';
    private const LEGACY_MM_TABLE = 'sys_products_fe_users_mm_visited_products';
    private const LOCAL_GLOBAL_TABLE = 'tx_products_visitedproduct';
    private const LOCAL_MM_TABLE = 'tx_products_fe_users_visitedproduct';

    private LegacyMigrationHelper $migrationHelper;

    private OutputInterface $output;

    public function injectMigrationHelper(LegacyMigrationHelper $migrationHelper): void
    {
        $this->migrationHelper = $migrationHelper;
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Visited-product counters must be fully migrated';
    }

    public function isFulfilled(): bool
    {
        if ($this->migrationHelper->tablesExist(self::LEGACY_GLOBAL_TABLE)) {
            if ($this->migrationHelper->countUnmigrated(self::LEGACY_GLOBAL_TABLE, self::LOCAL_GLOBAL_TABLE, false) > 0) {
                return false;
            }
        }
        if ($this->migrationHelper->tablesExist(self::LEGACY_MM_TABLE)) {
            if ($this->migrationHelper->countUnmigrated(self::LEGACY_MM_TABLE, self::LOCAL_MM_TABLE) > 0) {
                return false;
            }
        }
        return true;
    }

    public function ensure(): bool
    {
        $this->output->writeln(
            '<error>Visited-product tables still have unmigrated rows. Run the "products_ttProductsVisitedProductsMigration" '
                . 'upgrade wizard first, then retry.</error>'
        );
        return false;
    }
}
