<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use GoldeneZeiten\Products\Core\Backend\StorageFolderResolver;
use GoldeneZeiten\Products\Core\Updates\Prerequisites\ProductMigrationPrerequisite;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
// TODO: Migrate to TYPO3\CMS\Core\Attribute\UpgradeWizard once v13 support is dropped.
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Turns the storage folders a tt_products catalog was grouped by into categories: one category per folder
 * holding products, nested like the folders up to the first page that is not a folder, and every migrated
 * product assigned to the category of its folder. Opt-in through `migration.storageFolderCategories`; runs
 * after the product migration.
 *
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only
 * injects dependencies into a public service.
 */
#[Autoconfigure(public: true)]
#[UpgradeWizard('products_ttProductsStorageFolderCategories')]
final class TtProductsStorageFolderCategoryUpgradeWizard implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    private const SYSFOLDER = 254;

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly LegacyMigrationConfiguration $migrationConfiguration,
        private readonly StorageFolderResolver $storageFolderResolver,
        private readonly ProductMigrationPrerequisite $productMigrationPrerequisite,
        private readonly LegacyCategoryAssigner $categoryAssigner,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Turn the tt_products storage folders into categories';
    }

    public function getDescription(): string
    {
        return 'Creates a category for every storage folder holding tt_products records, nested like the folders, '
            . 'and assigns the migrated products. Runs only with the extension configuration '
            . 'migration.storageFolderCategories enabled.';
    }

    public function updateNecessary(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        foreach ($this->legacyProducts() as [$legacyProductUid, $folderUid]) {
            if ($this->isPending($legacyProductUid, $folderUid)) {
                return true;
            }
        }
        return false;
    }

    public function executeUpdate(): bool
    {
        if (!$this->isEnabled()) {
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
        foreach ($this->legacyProducts() as [$legacyProductUid, $folderUid]) {
            $productUid = $this->migrationHelper->resolveLocalUid('tt_products', $legacyProductUid, 'tx_products_domain_model_product');
            if ($productUid !== null && $productUid > 0) {
                $this->categoryAssigner->assign($productUid, $this->categoryForFolder($folderUid, $pid));
            }
        }
        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class, ProductMigrationPrerequisite::class];
    }

    private function isEnabled(): bool
    {
        return $this->migrationConfiguration->getBool('storageFolderCategories')
            && $this->migrationHelper->tablesExist('tt_products');
    }

    /**
     * @return array<int, array{0: int, 1: int}> [legacy product uid, folder uid]
     */
    private function legacyProducts(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_products');
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder->select('uid', 'pid')
            ->from('tt_products')
            ->where($queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('uid')
            ->executeQuery();
        $products = [];
        while ($row = $result->fetchAssociative()) {
            $products[] = [(int)$row['uid'], (int)$row['pid']];
        }
        return $products;
    }

    private function isPending(int $legacyProductUid, int $folderUid): bool
    {
        $productUid = $this->migrationHelper->resolveLocalUid('tt_products', $legacyProductUid, 'tx_products_domain_model_product');
        if ($productUid === null || $productUid === 0) {
            return false;
        }
        $categoryUid = $this->migrationHelper->resolveLocalUid('pages', $folderUid, 'tx_products_domain_model_category');
        return $categoryUid === null || !$this->categoryAssigner->isAssigned($productUid, $categoryUid);
    }

    /**
     * Creates the folder's category, and those of its parent folders, on first use.
     */
    private function categoryForFolder(int $folderUid, int $pid): int
    {
        $categoryUid = $this->migrationHelper->resolveLocalUid('pages', $folderUid, 'tx_products_domain_model_category');
        if ($categoryUid !== null) {
            return $categoryUid;
        }
        $folder = $this->fetchPage($folderUid);
        $parent = $this->fetchPage((int)($folder['pid'] ?? 0));
        $parentCategoryUid = (int)($parent['doktype'] ?? 0) === self::SYSFOLDER ? $this->categoryForFolder((int)$parent['uid'], $pid) : 0;
        $connection = $this->connectionPool->getConnectionForTable('tx_products_domain_model_category');
        $now = (int)($GLOBALS['EXEC_TIME'] ?? time());
        $connection->insert('tx_products_domain_model_category', [
            'tstamp' => $now,
            'crdate' => $now,
            'pid' => $pid,
            'title' => (string)($folder['title'] ?? ''),
            'slug' => basename((string)($folder['slug'] ?? '')),
            'parent_category' => $parentCategoryUid,
        ]);
        $categoryUid = (int)$connection->lastInsertId();
        $this->migrationHelper->recordMapping('pages', $folderUid, 'tx_products_domain_model_category', $categoryUid);
        return $categoryUid;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPage(int $pageUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();
        $page = $queryBuilder->select('uid', 'pid', 'doktype', 'title', 'slug')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        return is_array($page) ? $page : [];
    }
}
