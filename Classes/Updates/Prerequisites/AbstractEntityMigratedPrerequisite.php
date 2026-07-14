<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates\Prerequisites;

use GoldeneZeiten\Products\Updates\LegacyMigrationHelper;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\PrerequisiteInterface;

/**
 * Blocks a dependent upgrade wizard until an earlier entity migration has fully run.
 * `ensure()` deliberately never auto-runs the dependency wizard; it only reports the gap.
 *
 * Carries no service attribute of its own: an abstract class is never registered as a service, so each
 * concrete prerequisite declares that it is public itself.
 */
abstract class AbstractEntityMigratedPrerequisite implements PrerequisiteInterface, ChattyInterface
{
    protected LegacyMigrationHelper $migrationHelper;

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
        return sprintf('%s must be fully migrated', $this->legacyTable());
    }

    public function isFulfilled(): bool
    {
        if (!$this->migrationHelper->tablesExist($this->legacyTable())) {
            return true;
        }
        return $this->migrationHelper->countUnmigrated($this->legacyTable(), $this->localTable()) === 0;
    }

    public function ensure(): bool
    {
        $this->output->writeln(sprintf(
            '<error>%s still has unmigrated rows. Run the "%s" upgrade wizard first, then retry.</error>',
            $this->legacyTable(),
            $this->wizardIdentifier()
        ));
        return false;
    }

    abstract protected function legacyTable(): string;

    abstract protected function localTable(): string;

    abstract protected function wizardIdentifier(): string;
}
