<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
// EXT:install namespaces are valid through TYPO3 v14 (deprecated there); migrate to the
// TYPO3\CMS\Core\Attribute\UpgradeWizard / TYPO3\CMS\Core\Updates\* equivalents once v13 support is dropped.
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\ConfirmableInterface;
use TYPO3\CMS\Install\Updates\Confirmation;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Drops the legacy `tt_products` tables this extension's entity wizards (category/product/
 * article/order) actually migrate data from, once every one of them reports nothing left to do.
 * Explicit opt-in (ConfirmableInterface), never runs unattended, and refuses to drop anything
 * while a prerequisite wizard still has pending work.
 *
 * Media migration completeness is deliberately NOT part of the automated guard: a legacy row that
 * never had an image and one whose media just hasn't been migrated yet look identical (no
 * `sys_file_reference` either way, see LegacyMediaMigrator), so there is no reliable per-row signal
 * to check. Operators must ensure the media wizard has already been run; the confirmation dialog
 * says so explicitly.
 *
 * Deliberately NOT dropped: tables this extension never migrates at all (gifts, vouchers, the old
 * per-quantity graduated-price mechanism, the separate downloads catalog, accounts/cards, visited
 * products) - their data was never moved anywhere, so removing them here would be pure data loss
 * with no migrated copy to fall back on. See the Milestone 2 plan for that scope decision.
 */
#[UpgradeWizard('products_ttProductsLegacyCleanup')]
final class TtProductsLegacyCleanupUpgradeWizard implements UpgradeWizardInterface, ConfirmableInterface, ChattyInterface
{
    private const CATEGORY_TABLE = 'tt_products_cat';
    private const PRODUCT_TABLE = 'tt_products';
    private const ARTICLE_TABLE = 'tt_products_articles';
    private const ORDER_TABLE = 'sys_products_orders';

    /**
     * @var array{legacy: string, local: string}[]
     */
    private const ENTITY_TABLE_PAIRS = [
        ['legacy' => self::CATEGORY_TABLE, 'local' => 'tx_products_domain_model_category'],
        ['legacy' => self::PRODUCT_TABLE, 'local' => 'tx_products_domain_model_product'],
        ['legacy' => self::ARTICLE_TABLE, 'local' => 'tx_products_domain_model_article'],
        ['legacy' => self::ORDER_TABLE, 'local' => 'tx_products_domain_model_order'],
    ];

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
    ];

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
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
            . 'graduated-price mechanism, the downloads catalog, visited products) are left untouched.';
    }

    public function getConfirmation(): Confirmation
    {
        return new Confirmation(
            $this->getTitle(),
            'This permanently deletes the legacy tt_products tables. Only confirm once you have '
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
        if (!$this->allPrerequisitesComplete()) {
            $this->output->writeln(
                '<error>Not all tt_products data has been migrated yet; refusing to drop legacy tables.</error>'
            );
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
        return [DatabaseUpdatedPrerequisite::class];
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

    private function allPrerequisitesComplete(): bool
    {
        foreach (self::ENTITY_TABLE_PAIRS as $pair) {
            if ($this->migrationHelper->tablesExist($pair['legacy'])
                && $this->migrationHelper->countUnmigrated($pair['legacy'], $pair['local']) > 0
            ) {
                return false;
            }
        }
        return true;
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
