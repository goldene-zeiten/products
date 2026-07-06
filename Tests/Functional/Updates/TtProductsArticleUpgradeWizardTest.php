<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Updates\TtProductsArticleUpgradeWizard;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class TtProductsArticleUpgradeWizardTest extends FunctionalTestCase
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

    /**
     * @test
     */
    public function updateIsNecessaryInitially(): void
    {
        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function executeUpdateMigratesArticleWithResolvedProduct(): void
    {
        self::assertTrue($this->subject->executeUpdate());

        $articleUid = $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        self::assertNotNull($articleUid);
        self::assertSame(80, (int)$this->fetchField((int)$articleUid, 'product'));
        self::assertSame('9.99', $this->fetchField((int)$articleUid, 'price'));
        self::assertSame(10, (int)$this->fetchField((int)$articleUid, 'in_stock'));
    }

    /**
     * @test
     */
    public function articleWithMissingProductIsSkippedAndWarns(): void
    {
        self::assertTrue($this->subject->executeUpdate());

        self::assertSame(0, $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE));
        self::assertStringContainsString('referenced missing product uid 999', $this->output->fetch());
    }

    /**
     * @test
     */
    public function deletedArticleIsNeverMigrated(): void
    {
        $this->subject->executeUpdate();

        self::assertNull($this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 3, self::LOCAL_TABLE));
    }

    /**
     * @test
     */
    public function overlaysAreDeduplicatedAndOrphansAreSkipped(): void
    {
        $this->subject->executeUpdate();

        self::assertSame(1, $this->countRows(['title' => 'New DE']));
        self::assertSame(0, $this->countRows(['title' => 'Old DE']));
        self::assertSame(0, $this->countRows(['title' => 'Orphan overlay']));

        $output = $this->output->fetch();
        self::assertStringContainsString('Skipped duplicate tt_products_articles_language uid 10', $output);
        self::assertStringContainsString('parent uid 999 was never migrated', $output);
    }

    /**
     * @test
     */
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
}
