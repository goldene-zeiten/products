<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates\Prerequisites;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Public for use as an upgrade wizard prerequisite.
 */
#[Autoconfigure(public: true)]
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
