<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Updates\TtProductsArticleUpgradeWizard;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

final class TtProductsArticleUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-legacy-fixture',
    ];

    private const LEGACY_TABLE = 'tt_products_articles';
    private const LOCAL_TABLE = 'tx_products_domain_model_article';

    private TtProductsArticleUpgradeWizard $subject;
    private BufferedOutput $output;
    private LegacyMigrationHelper $migrationHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/LegacyMigration/tt_products_articles.csv');
        $this->migrationHelper = $this->get(LegacyMigrationHelper::class);
        $this->output = new BufferedOutput();
        $this->subject = $this->get(TtProductsArticleUpgradeWizard::class);
        $this->subject->setOutput($this->output);
    }

    #[Test]
    public function updateIsNecessaryInitially(): void
    {
        $this->assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateMigratesArticleWithResolvedProduct(): void
    {
        $this->assertTrue($this->subject->executeUpdate());

        $articleUid = $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        $this->assertNotNull($articleUid);
        $this->assertSame(80, (int)$this->fetchField((int)$articleUid, 'product'));
        $this->assertSame('9.99', $this->fetchField((int)$articleUid, 'price'));
        $this->assertSame(10, (int)$this->fetchField((int)$articleUid, 'in_stock'));
        $this->assertSame(2, (int)$this->fetchField((int)$articleUid, 'basket_min_quantity'));
        $this->assertSame(5, (int)$this->fetchField((int)$articleUid, 'basket_max_quantity'));
    }

    #[Test]
    public function isAddedPriceFlexFormFlagMapsToSurchargePriceMode(): void
    {
        $this->assertTrue($this->subject->executeUpdate());

        $withoutFlag = $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        $withFlag = $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 4, self::LOCAL_TABLE);
        $this->assertNotNull($withoutFlag);
        $this->assertNotNull($withFlag);
        $this->assertSame('override', $this->fetchField((int)$withoutFlag, 'price_mode'));
        $this->assertSame('surcharge', $this->fetchField((int)$withFlag, 'price_mode'));
    }

    #[Test]
    public function articleWithMissingProductIsSkippedAndWarns(): void
    {
        $this->assertTrue($this->subject->executeUpdate());

        $this->assertSame(0, $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE));
        $this->assertStringContainsString('referenced missing product uid 999', $this->output->fetch());
    }

    #[Test]
    public function deletedArticleIsNeverMigrated(): void
    {
        $this->subject->executeUpdate();

        $this->assertNull($this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 3, self::LOCAL_TABLE));
    }

    #[Test]
    public function overlaysAreDeduplicatedAndOrphansAreSkipped(): void
    {
        $this->subject->executeUpdate();

        $this->assertSame(1, $this->countRows(['title' => 'New DE']));
        $this->assertSame(0, $this->countRows(['title' => 'Old DE']));
        $this->assertSame(0, $this->countRows(['title' => 'Orphan overlay']));

        $output = $this->output->fetch();
        $this->assertStringContainsString('Skipped duplicate tt_products_articles_language uid 10', $output);
        $this->assertStringContainsString('parent uid 999 was never migrated', $output);
    }

    #[Test]
    public function executeUpdateIsIdempotent(): void
    {
        $this->subject->executeUpdate();
        $totalAfterFirstRun = $this->countRows([]);

        $this->assertFalse($this->subject->updateNecessary());
        $this->assertTrue($this->subject->executeUpdate());
        $this->assertSame($totalAfterFirstRun, $this->countRows([]));
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
}
