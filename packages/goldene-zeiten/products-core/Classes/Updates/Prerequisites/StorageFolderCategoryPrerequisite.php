<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates\Prerequisites;

use GoldeneZeiten\Products\Core\Updates\TtProductsStorageFolderCategoryUpgradeWizard;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\PrerequisiteInterface;

/**
 * Blocks the plugin migration while the storage folder categories are enabled but not created yet, since a
 * legacy element's starting point can only become a category selection once its folder is a category.
 *
 * Public for use as an upgrade wizard prerequisite.
 */
#[Autoconfigure(public: true)]
final class StorageFolderCategoryPrerequisite implements PrerequisiteInterface, ChattyInterface
{
    private TtProductsStorageFolderCategoryUpgradeWizard $storageFolderCategoryWizard;

    private OutputInterface $output;

    public function injectStorageFolderCategoryWizard(TtProductsStorageFolderCategoryUpgradeWizard $storageFolderCategoryWizard): void
    {
        $this->storageFolderCategoryWizard = $storageFolderCategoryWizard;
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'The tt_products storage folders must be turned into categories';
    }

    public function isFulfilled(): bool
    {
        return !$this->storageFolderCategoryWizard->updateNecessary();
    }

    public function ensure(): bool
    {
        $this->output->writeln(
            '<error>The storage folders are not turned into categories yet. Run the '
                . '"products_ttProductsStorageFolderCategories" upgrade wizard first, then retry.</error>'
        );
        return false;
    }
}
