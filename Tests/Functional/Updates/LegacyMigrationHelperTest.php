<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Updates\LegacyMigrationHelper;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class LegacyMigrationHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-legacy-fixture',
    ];

    private const LEGACY_TABLE = 'tt_products_cat';
    private const LOCAL_TABLE = 'tx_products_domain_model_category';

    private LegacyMigrationHelper $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(LegacyMigrationHelper::class);
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/LegacyMigration/legacy_migration_helper.csv');
    }

    /**
     * @test
     */
    public function tablesExistIsTrueForExistingTable(): void
    {
        self::assertTrue($this->subject->tablesExist(self::LEGACY_TABLE));
    }

    /**
     * @test
     */
    public function tablesExistIsFalseWhenAnyTableIsMissing(): void
    {
        self::assertFalse($this->subject->tablesExist(self::LEGACY_TABLE, 'tt_products_does_not_exist'));
    }

    /**
     * @test
     */
    public function countUnmigratedExcludesDeletedAndAlreadyMappedRows(): void
    {
        self::assertSame(1, $this->subject->countUnmigrated(self::LEGACY_TABLE, self::LOCAL_TABLE));
    }

    /**
     * @test
     */
    public function fetchUnmigratedBatchReturnsOnlyTheRemainingRow(): void
    {
        $rows = $this->subject->fetchUnmigratedBatch(self::LEGACY_TABLE, self::LOCAL_TABLE);

        self::assertCount(1, $rows);
        self::assertSame(2, (int)$rows[0]['uid']);
    }

    /**
     * @test
     */
    public function resolveLocalUidReturnsNullForUnmappedRow(): void
    {
        self::assertNull($this->subject->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE));
    }

    /**
     * @test
     */
    public function resolveLocalUidReturnsMappedUid(): void
    {
        self::assertSame(100, $this->subject->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE));
    }

    /**
     * @test
     */
    public function recordMappingMakesRowResolvableAndExcludedFromUnmigratedBatch(): void
    {
        $this->subject->recordMapping(self::LEGACY_TABLE, 2, self::LOCAL_TABLE, 200);

        self::assertSame(200, $this->subject->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE));
        self::assertSame(0, $this->subject->countUnmigrated(self::LEGACY_TABLE, self::LOCAL_TABLE));
    }
}
