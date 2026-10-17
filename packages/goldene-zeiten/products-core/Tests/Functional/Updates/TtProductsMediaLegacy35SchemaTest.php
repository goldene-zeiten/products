<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsMediaUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * tt_products 3.x keeps datasheets and category images in FAL only: `tt_products.datasheet` and
 * `tt_products_cat.image` no longer exist, only their `_uid` reference columns. The media wizard must work on
 * that schema - its `updateNecessary()` runs for every wizard list, so one unknown column breaks them all.
 *
 * Its own class, because it removes columns from the legacy schema for its test and restores them after.
 */
final class TtProductsMediaLegacy35SchemaTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $connectionPool = $this->get(ConnectionPool::class);
        foreach (['tt_products' => 'datasheet', 'tt_products_cat' => 'image'] as $table => $column) {
            $connection = $connectionPool->getConnectionForTable($table);
            if ($connection->createSchemaManager()->introspectTable($table)->hasColumn($column)) {
                $connection->executeStatement(sprintf(
                    'ALTER TABLE %s DROP COLUMN %s',
                    $connection->quoteIdentifier($table),
                    $connection->quoteIdentifier($column)
                ));
            }
        }
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsMediaLegacy35SchemaTest/tt_products_media.csv');
    }

    /**
     * Test classes with the same extensions share their instance and its schema, so the columns come back.
     */
    protected function tearDown(): void
    {
        $connectionPool = $this->get(ConnectionPool::class);
        foreach (['tt_products' => 'datasheet', 'tt_products_cat' => 'image'] as $table => $column) {
            $connection = $connectionPool->getConnectionForTable($table);
            if (!$connection->createSchemaManager()->introspectTable($table)->hasColumn($column)) {
                $connection->executeStatement(sprintf(
                    'ALTER TABLE %s ADD %s TEXT',
                    $connection->quoteIdentifier($table),
                    $connection->quoteIdentifier($column)
                ));
            }
        }
        parent::tearDown();
    }

    #[Test]
    public function theReferencesOfColumnsTheLegacySchemaLacksAreMigrated(): void
    {
        $subject = $this->get(TtProductsMediaUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/media_references_migrated.csv');
    }
}
