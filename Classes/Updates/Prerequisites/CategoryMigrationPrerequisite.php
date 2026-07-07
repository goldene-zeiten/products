<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates\Prerequisites;

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
