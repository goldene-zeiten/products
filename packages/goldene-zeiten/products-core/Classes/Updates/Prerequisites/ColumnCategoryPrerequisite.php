<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates\Prerequisites;

use GoldeneZeiten\Products\Core\Updates\TtProductsColumnCategoryUpgradeWizard;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\PrerequisiteInterface;

/**
 * Blocks the plugin migration while configured grouping columns are not turned into categories yet, since
 * a legacy product_where filter can only become a category selection once its values are categories.
 *
 * Public for use as an upgrade wizard prerequisite.
 */
#[Autoconfigure(public: true)]
final class ColumnCategoryPrerequisite implements PrerequisiteInterface, ChattyInterface
{
    private TtProductsColumnCategoryUpgradeWizard $columnCategoryWizard;

    private OutputInterface $output;

    public function injectColumnCategoryWizard(TtProductsColumnCategoryUpgradeWizard $columnCategoryWizard): void
    {
        $this->columnCategoryWizard = $columnCategoryWizard;
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'The tt_products grouping columns must be turned into categories';
    }

    public function isFulfilled(): bool
    {
        return !$this->columnCategoryWizard->updateNecessary();
    }

    public function ensure(): bool
    {
        $this->output->writeln(
            '<error>The grouping columns are not turned into categories yet. Run the '
                . '"products_ttProductsColumnCategories" upgrade wizard first, then retry.</error>'
        );
        return false;
    }
}
