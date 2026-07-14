<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates\Prerequisites;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Public for use as an upgrade wizard prerequisite.
 */
#[Autoconfigure(public: true)]
final class ArticleMigrationPrerequisite extends AbstractEntityMigratedPrerequisite
{
    protected function legacyTable(): string
    {
        return 'tt_products_articles';
    }

    protected function localTable(): string
    {
        return 'tx_products_domain_model_article';
    }

    protected function wizardIdentifier(): string
    {
        return 'products_ttProductsArticleMigration';
    }
}
