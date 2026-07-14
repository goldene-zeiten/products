<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use GoldeneZeiten\Products\Core\Backend\StorageFolderResolver;
use GoldeneZeiten\Products\Core\Updates\Prerequisites\ProductMigrationPrerequisite;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
// TODO: Migrate to TYPO3\CMS\Core\Attribute\UpgradeWizard once v13 support is dropped.
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Migrates `tt_products_articles` and overlays to `tx_products_domain_model_article`. Requires
 * {@see ProductMigrationPrerequisite} to have run first.
 *
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only
 * injects dependencies into a public service.
 */
#[Autoconfigure(public: true)]
#[UpgradeWizard('products_ttProductsArticleMigration')]
final class TtProductsArticleUpgradeWizard implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    private const LEGACY_TABLE = 'tt_products_articles';
    private const LEGACY_LANGUAGE_TABLE = 'tt_products_articles_language';
    private const LEGACY_PRODUCT_TABLE = 'tt_products';
    private const LOCAL_TABLE = 'tx_products_domain_model_article';
    private const LOCAL_PRODUCT_TABLE = 'tx_products_domain_model_product';

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly LegacyOverlayMigrator $overlayMigrator,
        private readonly StorageFolderResolver $storageFolderResolver,
        private readonly ProductMigrationPrerequisite $productMigrationPrerequisite,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Migrate tt_products_articles to tx_products_domain_model_article';
    }

    public function getDescription(): string
    {
        return 'Migrates legacy product articles and their tt_products_articles_language overlays, '
            . 'linking each article back to its already-migrated product.';
    }

    public function updateNecessary(): bool
    {
        if (!$this->migrationHelper->tablesExist(self::LEGACY_TABLE)) {
            return false;
        }
        return $this->migrationHelper->countUnmigrated(self::LEGACY_TABLE, self::LOCAL_TABLE) > 0
            || $this->overlayMigrator->hasPending($this->overlayConfig());
    }

    public function executeUpdate(): bool
    {
        if (!$this->migrationHelper->tablesExist(self::LEGACY_TABLE)) {
            return true;
        }
        if (!$this->productMigrationPrerequisite->isFulfilled()) {
            $this->output->writeln(
                '<error>tt_products still has unmigrated rows; run the product migration '
                    . 'wizard (products_ttProductsProductMigration) first.</error>'
            );
            return false;
        }
        $pid = $this->storageFolderResolver->resolve();
        while (($rows = $this->migrationHelper->fetchUnmigratedBatch(self::LEGACY_TABLE, self::LOCAL_TABLE)) !== []) {
            foreach ($rows as $row) {
                $this->migrateArticle($row, $pid);
            }
        }
        $this->overlayMigrator->migrate($this->output, $this->overlayConfig(), $this->overlayValues(...));
        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class, ProductMigrationPrerequisite::class];
    }

    private function overlayConfig(): OverlayMigrationConfig
    {
        return new OverlayMigrationConfig(
            legacyLanguageTable: self::LEGACY_LANGUAGE_TABLE,
            parentField: 'article_uid',
            legacyParentTable: self::LEGACY_TABLE,
            localTable: self::LOCAL_TABLE,
        );
    }

    /**
     * @param array<string, mixed> $legacyRow
     */
    private function migrateArticle(array $legacyRow, int $pid): void
    {
        $legacyUid = (int)$legacyRow['uid'];
        $productLocalUid = $this->migrationHelper->resolveLocalUid(
            self::LEGACY_PRODUCT_TABLE,
            (int)$legacyRow['uid_product'],
            self::LOCAL_PRODUCT_TABLE
        );
        if ($productLocalUid === null) {
            $this->output->writeln(sprintf(
                '<comment>tt_products_articles uid %d referenced missing product uid %d, skipped.</comment>',
                $legacyUid,
                (int)$legacyRow['uid_product']
            ));
            $this->migrationHelper->recordMapping(self::LEGACY_TABLE, $legacyUid, self::LOCAL_TABLE, 0);
            return;
        }
        $this->insertArticle($legacyRow, $pid, $legacyUid, $productLocalUid);
    }

    /**
     * @param array<string, mixed> $legacyRow
     */
    private function insertArticle(array $legacyRow, int $pid, int $legacyUid, int $productLocalUid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_TABLE);
        $queryBuilder->insert(self::LOCAL_TABLE)->values([
            'pid' => $pid,
            'hidden' => (int)$legacyRow['hidden'],
            'sys_language_uid' => 0,
            'product' => $productLocalUid,
            'title' => (string)$legacyRow['title'],
            'item_number' => (string)$legacyRow['itemnumber'],
            'price' => $this->formatPrice((string)$legacyRow['price']),
            'in_stock' => (int)$legacyRow['inStock'],
            'basket_min_quantity' => (int)round((float)($legacyRow['basketminquantity'] ?? 0)),
            'basket_max_quantity' => (int)round((float)($legacyRow['basketmaxquantity'] ?? 0)),
            'price_mode' => $this->isAddedPrice((string)($legacyRow['config'] ?? '')) ? 'surcharge' : 'override',
            'weight' => (int)round((float)$legacyRow['weight']),
        ])->executeStatement();
        $localUid = (int)$this->connectionPool->getConnectionForTable(self::LOCAL_TABLE)->lastInsertId();
        $this->migrationHelper->recordMapping(self::LEGACY_TABLE, $legacyUid, self::LOCAL_TABLE, $localUid);
    }

    private function formatPrice(string $legacyPrice): string
    {
        return number_format((float)$legacyPrice, 2, '.', '');
    }

    /**
     * `isAddedPrice` lives inside the FlexForm `config` blob; defaults to false if unparseable.
     */
    private function isAddedPrice(string $flexFormXml): bool
    {
        if (trim($flexFormXml) === '') {
            return false;
        }
        $parsed = GeneralUtility::xml2array($flexFormXml);
        if (!is_array($parsed)) {
            return false;
        }
        $value = $parsed['data']['sDEF']['lDEF']['isAddedPrice']['vDEF'] ?? null;
        return (bool)((int)$value);
    }

    /**
     * price_mode is set explicitly; the TCA schema default isn't applied consistently across
     * supported core majors.
     *
     * @param array<string, mixed> $winner
     * @return array<string, mixed>
     */
    private function overlayValues(array $winner): array
    {
        return [
            'title' => (string)$winner['title'],
            'price_mode' => 'override',
        ];
    }
}
