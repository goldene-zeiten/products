<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Updates\TtProductsPluginUpgradeWizard;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * The plugin wizard finds legacy elements through tt_content.list_type, which TYPO3 v14 removed
 * (core #105538). A non-destructive database compare keeps the column, so the common v14 case is
 * covered by the main test. This covers the other case - an operator who ran a destructive compare
 * and dropped the column before migrating - where the wizard must report the loss, not fatal on a
 * missing column. The column is dropped here explicitly so the behaviour is identical on v13 and v14.
 */
final class TtProductsPluginUpgradeWizardWithoutListTypeColumnTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->dropListTypeColumn();
    }

    private function subject(BufferedOutput $output): TtProductsPluginUpgradeWizard
    {
        $subject = $this->get(TtProductsPluginUpgradeWizard::class);
        $subject->setOutput($output);
        return $subject;
    }

    #[Test]
    public function updateIsNotNecessaryWithoutTheListTypeColumn(): void
    {
        $this->assertFalse($this->subject(new BufferedOutput())->updateNecessary());
    }

    #[Test]
    public function executeReportsTheMissingColumnInsteadOfFailing(): void
    {
        $output = new BufferedOutput();

        $this->assertTrue($this->subject($output)->executeUpdate());
        $this->assertStringContainsString('list_type', $output->fetch());
    }

    private function dropListTypeColumn(): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tt_content');
        foreach ($connection->createSchemaManager()->listTableColumns('tt_content') as $column) {
            if ($column->getName() === 'list_type') {
                $connection->executeStatement('ALTER TABLE tt_content DROP COLUMN list_type');
                return;
            }
        }
    }
}
