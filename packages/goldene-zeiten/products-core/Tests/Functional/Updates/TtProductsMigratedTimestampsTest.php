<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Core\Updates\TtProductsArticleUpgradeWizard;
use GoldeneZeiten\Products\Core\Updates\TtProductsCategoryUpgradeWizard;
use GoldeneZeiten\Products\Core\Updates\TtProductsProductUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A migrated record keeps the age it had in tt_products: the product sitemap reports it as `lastmod`
 * and the "new" list mode selects by `crdate`.
 */
final class TtProductsMigratedTimestampsTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsMigratedTimestampsTest/legacy_records.csv');
    }

    /**
     * @param class-string $wizardClass
     */
    #[Test]
    #[DataProvider('recordProvider')]
    public function migratedRecordKeepsLegacyTimestamps(string $wizardClass, string $localTable, string $title, int $expectedTstamp, int $expectedCrdate): void
    {
        // Products wait for the categories and articles for the products, so each case migrates its prerequisites first.
        foreach (array_unique([TtProductsCategoryUpgradeWizard::class, TtProductsProductUpgradeWizard::class, $wizardClass]) as $class) {
            $wizard = $this->get($class);
            $wizard->setOutput(new BufferedOutput());
            $wizard->executeUpdate();
        }

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($localTable);
        $row = $queryBuilder->select('tstamp', 'crdate')
            ->from($localTable)
            ->where($queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter($title)))
            ->executeQuery()
            ->fetchAssociative();

        $this->assertSame(['tstamp' => $expectedTstamp, 'crdate' => $expectedCrdate], array_map(intval(...), $row ?: []));
    }

    public static function recordProvider(): \Generator
    {
        $targets = [
            'product' => [TtProductsProductUpgradeWizard::class, 'tx_products_domain_model_product'],
            'article' => [TtProductsArticleUpgradeWizard::class, 'tx_products_domain_model_article'],
            'category' => [TtProductsCategoryUpgradeWizard::class, 'tx_products_domain_model_category'],
        ];
        foreach ($targets as $name => [$wizardClass, $localTable]) {
            yield $name . ' keeps tstamp and crdate' => [$wizardClass, $localTable, 'Kept', 1600000500, 1600000100];
            yield $name . ' falls back to tstamp without crdate' => [$wizardClass, $localTable, 'Crdate missing', 1600000500, 1600000500];
        }
    }

    #[Test]
    public function timestampsFallBackToTheCurrentTimeWithoutAnyLegacyTimestamp(): void
    {
        $execTime = $GLOBALS['EXEC_TIME'];
        $GLOBALS['EXEC_TIME'] = 1700000000;

        $timestamps = $this->get(LegacyMigrationHelper::class)->timestamps(['tstamp' => 0, 'crdate' => 0]);
        $GLOBALS['EXEC_TIME'] = $execTime;

        $this->assertSame(['tstamp' => 1700000000, 'crdate' => 1700000000], $timestamps);
    }

    /**
     * @param array<string, int> $legacyRow
     * @param array{tstamp: int, crdate: int} $expected
     */
    #[Test]
    #[DataProvider('legacyTimestampProvider')]
    public function timestampsIgnorePlaceholderValuesBeforeTheYear2000(array $legacyRow, array $expected): void
    {
        $execTime = $GLOBALS['EXEC_TIME'];
        $GLOBALS['EXEC_TIME'] = 1700000000;

        $timestamps = $this->get(LegacyMigrationHelper::class)->timestamps($legacyRow);
        $GLOBALS['EXEC_TIME'] = $execTime;

        $this->assertSame($expected, $timestamps);
    }

    public static function legacyTimestampProvider(): \Generator
    {
        yield 'placeholder tstamp and empty crdate fall back to the current time' => [
            'legacyRow' => ['tstamp' => 1, 'crdate' => 0],
            'expected' => ['tstamp' => 1700000000, 'crdate' => 1700000000],
        ];
        yield 'placeholder tstamp takes a real crdate' => [
            'legacyRow' => ['tstamp' => 1, 'crdate' => 1600000100],
            'expected' => ['tstamp' => 1600000100, 'crdate' => 1600000100],
        ];
        yield 'placeholder crdate falls back to a real tstamp' => [
            'legacyRow' => ['tstamp' => 1600000500, 'crdate' => 5],
            'expected' => ['tstamp' => 1600000500, 'crdate' => 1600000500],
        ];
    }
}
