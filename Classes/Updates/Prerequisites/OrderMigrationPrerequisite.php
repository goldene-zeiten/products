<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates\Prerequisites;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Public for use as an upgrade wizard prerequisite.
 */
#[Autoconfigure(public: true)]
final class OrderMigrationPrerequisite extends AbstractEntityMigratedPrerequisite
{
    protected function legacyTable(): string
    {
        return 'sys_products_orders';
    }

    protected function localTable(): string
    {
        return 'tx_products_domain_model_order';
    }

    protected function wizardIdentifier(): string
    {
        return 'products_ttProductsOrderMigration';
    }
}
