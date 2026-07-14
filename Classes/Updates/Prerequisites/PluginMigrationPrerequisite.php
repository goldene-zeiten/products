<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates\Prerequisites;

use GoldeneZeiten\Products\Updates\TtProductsPluginUpgradeWizard;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\PrerequisiteInterface;

/**
 * Blocks dependent wizards while legacy tt_products plugin elements are still migratable. Elements
 * whose modes have no equivalent here stay in tt_content by design, so they must not count as
 * pending - the wizard itself decides what is left to do.
 *
 * Public for use as an upgrade wizard prerequisite.
 */
#[Autoconfigure(public: true)]
final class PluginMigrationPrerequisite implements PrerequisiteInterface, ChattyInterface
{
    private TtProductsPluginUpgradeWizard $pluginUpgradeWizard;

    private OutputInterface $output;

    public function injectPluginUpgradeWizard(TtProductsPluginUpgradeWizard $pluginUpgradeWizard): void
    {
        $this->pluginUpgradeWizard = $pluginUpgradeWizard;
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Legacy tt_products plugin elements must be migrated';
    }

    public function isFulfilled(): bool
    {
        return !$this->pluginUpgradeWizard->updateNecessary();
    }

    public function ensure(): bool
    {
        $this->output->writeln(
            '<error>Legacy tt_products plugin content elements are still in place. Run the '
                . '"products_ttProductsPluginMigration" upgrade wizard first, then retry.</error>'
        );
        return false;
    }
}
