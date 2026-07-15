<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * A migration that moved out of the core into an optional add-on leaves an installation that upgraded the
 * core alone with no wizard for its legacy data, which it would then silently never migrate.
 *
 * Each externalized migration gets a small concrete subclass of this as its own core-side notice: when the
 * legacy data is still present while the add-on is not installed, the notice reports as necessary and names
 * the extension to install. It performs no migration itself - that lives in the add-on - so it is
 * repeatable and steps aside the moment the add-on is installed, when the add-on's own wizard takes over.
 *
 * @see \GoldeneZeiten\Products\Core\Updates\ExternalizedMigration for how a subclass declares its data.
 */
abstract class AbstractExternalizedMigrationNoticeUpgradeWizard implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    protected ConnectionPool $connectionPool;
    protected PackageManager $packageManager;
    protected OutputInterface $output;

    public function injectConnectionPool(ConnectionPool $connectionPool): void
    {
        $this->connectionPool = $connectionPool;
    }

    public function injectPackageManager(PackageManager $packageManager): void
    {
        $this->packageManager = $packageManager;
    }

    /**
     * The legacy data this migration moved, and the add-on it moved into.
     */
    abstract protected function externalizedMigration(): ExternalizedMigration;

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return sprintf(
            'Legacy tt_products data migrated by the optional "%s" extension',
            $this->externalizedMigration()->package,
        );
    }

    public function getDescription(): string
    {
        $migration = $this->externalizedMigration();

        return sprintf(
            'Legacy data for %s is still present. Its migration now lives in the extension "%s" (%s) - '
            . 'install and activate it, then run its upgrade wizard, to migrate this data. Ignore this if '
            . 'you do not use that feature.',
            $migration->feature,
            $migration->package,
            $migration->extensionKey,
        );
    }

    public function updateNecessary(): bool
    {
        $migration = $this->externalizedMigration();

        return !$this->packageManager->isPackageActive($migration->extensionKey)
            && $this->legacyDataExists($migration);
    }

    public function executeUpdate(): bool
    {
        // Advisory only: the migration itself lives in the add-on, so there is nothing to run here. The
        // notice stands (it is repeatable) until the add-on is installed, which is when updateNecessary()
        // turns it off and the add-on's own wizard takes over.
        $migration = $this->externalizedMigration();
        $this->output->writeln(sprintf(
            'Install "%s" to migrate the legacy %s data.',
            $migration->package,
            $migration->feature,
        ));

        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [];
    }

    private function legacyDataExists(ExternalizedMigration $migration): bool
    {
        $connection = $this->connectionPool->getConnectionForTable($migration->legacyTable);
        if (!$connection->createSchemaManager()->tablesExist([$migration->legacyTable])) {
            return false;
        }

        if ($migration->legacyColumn !== null) {
            return $this->columnIsPopulated($migration->legacyTable, $migration->legacyColumn);
        }

        return (int)$connection->createQueryBuilder()
            ->count('*')
            ->from($migration->legacyTable)
            ->executeQuery()
            ->fetchOne() > 0;
    }

    /**
     * True if any row carries a meaningful value in the column - not null, not the empty string, not "0".
     * Reading the distinct values and judging them in PHP keeps this correct across every DBMS without
     * casting the column to a comparable type in SQL, which is where cross-platform type rules bite.
     */
    private function columnIsPopulated(string $table, string $column): bool
    {
        $values = $this->connectionPool
            ->getConnectionForTable($table)
            ->createQueryBuilder()
            ->select($column)
            ->distinct()
            ->from($table)
            ->executeQuery()
            ->fetchFirstColumn();

        foreach ($values as $value) {
            if ($value !== null && (string)$value !== '' && (string)$value !== '0') {
                return true;
            }
        }

        return false;
    }
}
