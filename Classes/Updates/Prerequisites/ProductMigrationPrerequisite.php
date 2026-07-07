<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates\Prerequisites;

final class ProductMigrationPrerequisite extends AbstractEntityMigratedPrerequisite
{
    protected function legacyTable(): string
    {
        return 'tt_products';
    }

    protected function localTable(): string
    {
        return 'tx_products_domain_model_product';
    }

    protected function wizardIdentifier(): string
    {
        return 'products_ttProductsProductMigration';
    }
}
