<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates\Prerequisites;

use GoldeneZeiten\Products\Updates\LegacyMigrationHelper;
use Symfony\Component\Console\Output\OutputInterface;
// EXT:install namespaces are valid through TYPO3 v14 (deprecated there); migrate to the
// TYPO3\CMS\Core\Attribute\UpgradeWizard / TYPO3\CMS\Core\Updates\* equivalents once v13 support is dropped.
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\PrerequisiteInterface;

/**
 * Blocks a dependent upgrade wizard until an earlier entity migration has fully run. `ensure()`
 * deliberately never runs the dependency wizard itself - core's PrerequisiteInterface::ensure() is
 * meant for auto-fixing environment-level gates (e.g. DatabaseUpdatedPrerequisite), and silently
 * running another wizard's executeUpdate() here would hide migration order from the operator
 * instead of surfacing it.
 *
 * This only guards the CLI `upgrade:run` command, since that is the only place core calls
 * getPrerequisites()/ensure() (UpgradeWizardRunCommand::handlePrerequisites()); the backend Install
 * Tool UI never consults it. Dependent wizards therefore also call isFulfilled() directly from their
 * own executeUpdate() to stay guarded there too.
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
