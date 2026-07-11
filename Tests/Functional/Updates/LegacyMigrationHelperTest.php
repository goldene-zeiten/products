<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Updates\LegacyMigrationHelper;
use PHPUnit\Framework\Attributes\Test;

final class LegacyMigrationHelperTest extends AbstractFunctionalTestCase
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

    #[Test]
    public function tablesExistIsTrueForExistingTable(): void
    {
        $this->assertTrue($this->subject->tablesExist(self::LEGACY_TABLE));
    }

    #[Test]
    public function tablesExistIsFalseWhenAnyTableIsMissing(): void
    {
        $this->assertFalse($this->subject->tablesExist(self::LEGACY_TABLE, 'tt_products_does_not_exist'));
    }

    #[Test]
    public function countUnmigratedExcludesDeletedAndAlreadyMappedRows(): void
    {
        $this->assertSame(1, $this->subject->countUnmigrated(self::LEGACY_TABLE, self::LOCAL_TABLE));
    }

    #[Test]
    public function fetchUnmigratedBatchReturnsOnlyTheRemainingRow(): void
    {
        $rows = $this->subject->fetchUnmigratedBatch(self::LEGACY_TABLE, self::LOCAL_TABLE);

        $this->assertCount(1, $rows);
        $this->assertSame(2, (int)$rows[0]['uid']);
    }

    #[Test]
    public function resolveLocalUidReturnsNullForUnmappedRow(): void
    {
        $this->assertNull($this->subject->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE));
    }

    #[Test]
    public function resolveLocalUidReturnsMappedUid(): void
    {
        $this->assertSame(100, $this->subject->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE));
    }

    #[Test]
    public function recordMappingMakesRowResolvableAndExcludedFromUnmigratedBatch(): void
    {
        $this->subject->recordMapping(self::LEGACY_TABLE, 2, self::LOCAL_TABLE, 200);

        $this->assertSame(200, $this->subject->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE));
        $this->assertSame(0, $this->subject->countUnmigrated(self::LEGACY_TABLE, self::LOCAL_TABLE));
    }
}
