<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates\Prerequisites;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Public for use as an upgrade wizard prerequisite.
 */
#[Autoconfigure(public: true)]
final class CategoryMigrationPrerequisite extends AbstractEntityMigratedPrerequisite
{
    protected function legacyTable(): string
    {
        return 'tt_products_cat';
    }

    protected function localTable(): string
    {
        return 'tx_products_domain_model_category';
    }

    protected function wizardIdentifier(): string
    {
        return 'products_ttProductsCategoryMigration';
    }
}
