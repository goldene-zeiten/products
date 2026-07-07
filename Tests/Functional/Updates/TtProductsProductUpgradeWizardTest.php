<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Updates\TtProductsProductUpgradeWizard;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

final class TtProductsProductUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-legacy-fixture',
    ];

    private const LEGACY_TABLE = 'tt_products';
    private const LOCAL_TABLE = 'tx_products_domain_model_product';
    private const LOCAL_CATEGORY_MM_TABLE = 'tx_products_product_category_mm';

    private TtProductsProductUpgradeWizard $subject;
    private BufferedOutput $output;
    private LegacyMigrationHelper $migrationHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/LegacyMigration/tt_products.csv');
        $this->migrationHelper = $this->get(LegacyMigrationHelper::class);
        $this->output = new BufferedOutput();
        $this->subject = $this->get(TtProductsProductUpgradeWizard::class);
        $this->subject->setOutput($this->output);
    }

    #[Test]
    public function updateIsNecessaryInitially(): void
    {
        self::assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateMigratesVisibleProductsExcludingDeleted(): void
    {
        self::assertTrue($this->subject->executeUpdate());

        self::assertSame(3, $this->countRows(['sys_language_uid' => 0]));
        self::assertNull($this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 4, self::LOCAL_TABLE));
    }

    #[Test]
    public function priceIsFormattedAsADecimalString(): void
    {
        $this->subject->executeUpdate();

        $productUid = $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        self::assertSame('19.99', $this->fetchField((int)$productUid, 'price'));
    }

    #[Test]
    public function taxCategoryIsMappedToTheResolvedTaxClass(): void
    {
        $this->subject->executeUpdate();

        $standardProduct = $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        $reducedProduct = $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE);
        $unknownTaxcatProduct = $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 3, self::LOCAL_TABLE);

        self::assertSame(1, (int)$this->fetchField((int)$standardProduct, 'tax_class'));
        self::assertSame(2, (int)$this->fetchField((int)$reducedProduct, 'tax_class'));
        self::assertSame(1, (int)$this->fetchField((int)$unknownTaxcatProduct, 'tax_class'));
        self::assertStringContainsString('unknown taxcat_id 9', $this->output->fetch());
    }

    #[Test]
    public function categoryIsLinkedViaTheMigrationMap(): void
    {
        $this->subject->executeUpdate();

        $productUid = (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        self::assertSame(1, $this->countMmRows($productUid, 50));
    }

    #[Test]
    public function orphanCategoryIsLeftUnassignedAndWarns(): void
    {
        $this->subject->executeUpdate();

        $productUid = (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE);
        self::assertSame(0, $this->countMmRows($productUid, null));
        self::assertStringContainsString('referenced missing category uid 999', $this->output->fetch());
    }

    #[Test]
    public function presentImageTriggersAnOutOfScopeNotice(): void
    {
        $this->subject->executeUpdate();

        self::assertStringContainsString('tt_products uid 2 had an image', $this->output->fetch());
    }

    #[Test]
    public function overlaysAreDeduplicatedAndOrphansAreSkipped(): void
    {
        $this->subject->executeUpdate();

        self::assertSame(2, $this->countRows(['sys_language_uid' => 1]));
        self::assertSame(1, $this->countRows(['title' => 'New DE']));
        self::assertSame(0, $this->countRows(['title' => 'Old DE']));
        self::assertSame(0, $this->countRows(['title' => 'Orphan overlay']));

        $output = $this->output->fetch();
        self::assertStringContainsString('Skipped duplicate tt_products_language uid 20', $output);
        self::assertStringContainsString('parent uid 999 was never migrated', $output);
    }

    #[Test]
    public function executeUpdateIsIdempotent(): void
    {
        $this->subject->executeUpdate();
        $totalAfterFirstRun = $this->countRows([]);

        self::assertFalse($this->subject->updateNecessary());
        self::assertTrue($this->subject->executeUpdate());
        self::assertSame($totalAfterFirstRun, $this->countRows([]));
    }

    /**
     * @param array<string, int|string> $where
     */
    private function countRows(array $where): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::LOCAL_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->count('uid')->from(self::LOCAL_TABLE);
        foreach ($where as $field => $value) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value)));
        }
        return (int)$queryBuilder->executeQuery()->fetchOne();
    }

    private function fetchField(int $uid, string $field): mixed
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::LOCAL_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select($field)->from(self::LOCAL_TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid)))
            ->executeQuery()->fetchOne();
    }

    private function countMmRows(int $productUid, ?int $categoryUid): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::LOCAL_CATEGORY_MM_TABLE);
        $queryBuilder->count('uid_local')->from(self::LOCAL_CATEGORY_MM_TABLE)
            ->where($queryBuilder->expr()->eq('uid_local', $queryBuilder->createNamedParameter($productUid)));
        if ($categoryUid !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($categoryUid)));
        }
        return (int)$queryBuilder->executeQuery()->fetchOne();
    }
}
