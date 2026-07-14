<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Core\Updates\LegacyMigrationHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class LegacyMigrationHelperTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
        'goldene-zeiten/products-legacy-fixture',
    ];

    private const LEGACY_TABLE = 'tt_products_cat';
    private const LOCAL_TABLE = 'tx_products_domain_model_category';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyMigrationHelperTest/legacy_migration_helper.csv');
    }

    /**
     * @param string[] $legacyTables
     */
    #[Test]
    #[DataProvider('tablesExistProvider')]
    public function tablesExistReflectsWhetherAllGivenTablesArePresent(array $legacyTables, bool $expectedResult): void
    {
        $subject = $this->get(LegacyMigrationHelper::class);
        $this->assertSame($expectedResult, $subject->tablesExist(...$legacyTables));
    }

    /**
     * @return \Generator<string, array{legacyTables: string[], expectedResult: bool}>
     */
    public static function tablesExistProvider(): \Generator
    {
        yield 'existing table' => ['legacyTables' => [self::LEGACY_TABLE], 'expectedResult' => true];
        yield 'any missing table' => [
            'legacyTables' => [self::LEGACY_TABLE, 'tt_products_does_not_exist'],
            'expectedResult' => false,
        ];
    }

    #[Test]
    public function countUnmigratedExcludesDeletedAndAlreadyMappedRows(): void
    {
        $subject = $this->get(LegacyMigrationHelper::class);
        $this->assertSame(1, $subject->countUnmigrated(self::LEGACY_TABLE, self::LOCAL_TABLE));
    }

    #[Test]
    public function fetchUnmigratedBatchReturnsOnlyTheRemainingRow(): void
    {
        $subject = $this->get(LegacyMigrationHelper::class);
        $rows = $subject->fetchUnmigratedBatch(self::LEGACY_TABLE, self::LOCAL_TABLE);

        $this->assertCount(1, $rows);
        $this->assertSame(2, (int)$rows[0]['uid']);
    }

    #[Test]
    #[DataProvider('legacyUidToResolvedLocalUidProvider')]
    public function resolveLocalUidResolvesOrReturnsNull(int $legacyUid, ?int $expectedLocalUid): void
    {
        $subject = $this->get(LegacyMigrationHelper::class);
        $this->assertSame($expectedLocalUid, $subject->resolveLocalUid(self::LEGACY_TABLE, $legacyUid, self::LOCAL_TABLE));
    }

    /**
     * @return \Generator<string, array{legacyUid: int, expectedLocalUid: int|null}>
     */
    public static function legacyUidToResolvedLocalUidProvider(): \Generator
    {
        yield 'unmapped row resolves to null' => ['legacyUid' => 2, 'expectedLocalUid' => null];
        yield 'mapped row resolves to local uid' => ['legacyUid' => 1, 'expectedLocalUid' => 100];
    }

    #[Test]
    public function recordMappingMakesRowResolvableAndExcludedFromUnmigratedBatch(): void
    {
        $subject = $this->get(LegacyMigrationHelper::class);
        $subject->recordMapping(self::LEGACY_TABLE, 2, self::LOCAL_TABLE, 200);

        $this->assertSame(200, $subject->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE));
        $this->assertSame(0, $subject->countUnmigrated(self::LEGACY_TABLE, self::LOCAL_TABLE));
    }
}
