<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

use GoldeneZeiten\Products\Updates\Prerequisites\ArticleMigrationPrerequisite;
use GoldeneZeiten\Products\Updates\Prerequisites\CategoryMigrationPrerequisite;
use GoldeneZeiten\Products\Updates\Prerequisites\OrderMigrationPrerequisite;
use GoldeneZeiten\Products\Updates\Prerequisites\ProductMigrationPrerequisite;
use GoldeneZeiten\Products\Updates\Prerequisites\VisitedProductsMigrationPrerequisite;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\ConnectionPool;
// TODO: Migrate to TYPO3\CMS\Core\Attribute\UpgradeWizard once v13 support is dropped.
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\ConfirmableInterface;
use TYPO3\CMS\Install\Updates\Confirmation;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Drops legacy `tt_products` tables and visited-product tracking tables once all entity wizards
 * report nothing left to migrate. Media migration completeness cannot be checked automatically
 * (no reliable per-row signal), so operators must confirm the media wizard already ran.
 *
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only
 * injects dependencies into a public service.
 */
#[Autoconfigure(public: true)]
#[UpgradeWizard('products_ttProductsLegacyCleanup')]
final class TtProductsLegacyCleanupUpgradeWizard implements UpgradeWizardInterface, ConfirmableInterface, ChattyInterface
{
    private const CATEGORY_TABLE = 'tt_products_cat';
    private const PRODUCT_TABLE = 'tt_products';
    private const ARTICLE_TABLE = 'tt_products_articles';
    private const ORDER_TABLE = 'sys_products_orders';

    /**
     * @var string[]
     */
    private const TABLES_TO_DROP = [
        'tt_products_language',
        self::CATEGORY_TABLE,
        'tt_products_cat_language',
        self::PRODUCT_TABLE,
        self::ARTICLE_TABLE,
        'tt_products_articles_language',
        self::ORDER_TABLE,
        'sys_products_orders_mm_tt_products',
        'sys_products_visited_products',
        'sys_products_fe_users_mm_visited_products',
    ];

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly CategoryMigrationPrerequisite $categoryMigrationPrerequisite,
        private readonly ProductMigrationPrerequisite $productMigrationPrerequisite,
        private readonly ArticleMigrationPrerequisite $articleMigrationPrerequisite,
        private readonly OrderMigrationPrerequisite $orderMigrationPrerequisite,
        private readonly VisitedProductsMigrationPrerequisite $visitedProductsMigrationPrerequisite,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Drop migrated tt_products legacy tables';
    }

    public function getDescription(): string
    {
        return 'Drops the legacy tt_products/tt_products_cat/tt_products_articles/sys_products_orders '
            . 'tables (and their _language/mm siblings) once every migration wizard reports nothing '
            . 'left to migrate. Tables this extension never migrates (gifts, vouchers, the old '
            . 'graduated-price mechanism) are left untouched.';
    }

    public function getConfirmation(): Confirmation
    {
        return new Confirmation(
            $this->getTitle(),
            'This permanently deletes the legacy tt_products and visited-product tables. Only confirm once you have '
                . 'verified the migrated catalog, articles and orders in the new tables AND run the '
                . 'media migration wizard - media completeness cannot be checked automatically.',
            false,
            'Yes, drop the legacy tables',
            'No, keep them'
        );
    }

    public function updateNecessary(): bool
    {
        return $this->anyLegacyTableExists();
    }

    public function executeUpdate(): bool
    {
        if (!$this->anyLegacyTableExists()) {
            return true;
        }
        if (!$this->prerequisitesFulfilled()) {
            return false;
        }
        foreach (self::TABLES_TO_DROP as $table) {
            $this->dropTableIfExists($table);
        }
        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
            CategoryMigrationPrerequisite::class,
            ProductMigrationPrerequisite::class,
            ArticleMigrationPrerequisite::class,
            OrderMigrationPrerequisite::class,
            VisitedProductsMigrationPrerequisite::class,
        ];
    }

    private function anyLegacyTableExists(): bool
    {
        foreach (self::TABLES_TO_DROP as $table) {
            if ($this->migrationHelper->tablesExist($table)) {
                return true;
            }
        }
        return false;
    }

    private function prerequisitesFulfilled(): bool
    {
        $fulfilled = $this->categoryMigrationPrerequisite->isFulfilled()
            && $this->productMigrationPrerequisite->isFulfilled()
            && $this->articleMigrationPrerequisite->isFulfilled()
            && $this->orderMigrationPrerequisite->isFulfilled()
            && $this->visitedProductsMigrationPrerequisite->isFulfilled();
        if (!$fulfilled) {
            $this->output->writeln(
                '<error>Not all tt_products data has been migrated yet; refusing to drop legacy tables.</error>'
            );
        }
        return $fulfilled;
    }

    private function dropTableIfExists(string $table): void
    {
        if (!$this->migrationHelper->tablesExist($table)) {
            return;
        }
        $this->connectionPool->getConnectionForTable($table)->createSchemaManager()->dropTable($table);
        $this->output->writeln(sprintf('<info>Dropped legacy table "%s".</info>', $table));
    }
}
