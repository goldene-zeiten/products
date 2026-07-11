<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Updates\TtProductsCategoryUpgradeWizard;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

final class TtProductsCategoryUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-legacy-fixture',
    ];

    private const LEGACY_TABLE = 'tt_products_cat';
    private const LOCAL_TABLE = 'tx_products_domain_model_category';

    private TtProductsCategoryUpgradeWizard $subject;
    private BufferedOutput $output;
    private LegacyMigrationHelper $migrationHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/LegacyMigration/tt_products_cat.csv');
        $this->migrationHelper = $this->get(LegacyMigrationHelper::class);
        $this->output = new BufferedOutput();
        $this->subject = $this->get(TtProductsCategoryUpgradeWizard::class);
        $this->subject->setOutput($this->output);
    }

    #[Test]
    public function updateIsNecessaryInitially(): void
    {
        $this->assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateMigratesVisibleCategoriesExcludingDeleted(): void
    {
        $this->assertTrue($this->subject->executeUpdate());

        $this->assertSame(4, $this->countRows(['sys_language_uid' => 0]));
        $this->assertNull($this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 4, self::LOCAL_TABLE));
    }

    #[Test]
    public function parentCategoryIsReLinkedViaTheMigrationMap(): void
    {
        $this->subject->executeUpdate();

        $rootLocalUid = $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        $childLocalUid = $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE);

        $this->assertSame($rootLocalUid, (int)$this->fetchField((int)$childLocalUid, 'parent_category'));
    }

    #[Test]
    public function orphanParentIsLinkedToRootAndWarns(): void
    {
        $this->subject->executeUpdate();

        $orphanLocalUid = $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 3, self::LOCAL_TABLE);
        $this->assertSame(0, (int)$this->fetchField((int)$orphanLocalUid, 'parent_category'));
        $this->assertStringContainsString('referenced missing parent uid 999', $this->output->fetch());
    }

    #[Test]
    public function overlaysAreDeduplicatedPreferringVisibleThenHighestUid(): void
    {
        $this->subject->executeUpdate();

        $this->assertSame(2, $this->countRows(['sys_language_uid' => 1]));
        $this->assertSame(1, $this->countRows(['sys_language_uid' => 2]));
        $this->assertSame(0, $this->countRows(['sys_language_uid' => 3]));
        $this->assertSame(1, $this->countRows(['title' => 'Child DE visible']));
        $this->assertSame(0, $this->countRows(['title' => 'Child DE hidden old']));
        $this->assertSame(1, $this->countRows(['title' => 'Child FR hidden 2']));
        $this->assertSame(0, $this->countRows(['title' => 'Child FR hidden 1']));

        $output = $this->output->fetch();
        $this->assertStringContainsString('Skipped duplicate tt_products_cat_language uid 20', $output);
        $this->assertStringContainsString('Skipped duplicate tt_products_cat_language uid 30', $output);
    }

    #[Test]
    public function overlayWithOrphanParentIsSkippedAndWarns(): void
    {
        $this->subject->executeUpdate();

        $this->assertSame(0, $this->countRows(['title' => 'Orphan overlay']));
        $this->assertStringContainsString('parent uid 4 was never migrated', $this->output->fetch());
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
