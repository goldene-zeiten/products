<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Command;

use GoldeneZeiten\Products\Core\Command\InvoiceRetentionCleanupCommand;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class InvoiceRetentionCleanupCommandTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    #[Test]
    public function dryRunDoesNotDeleteAnything(): void
    {
        $this->setUpProductsSite();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/invoice_retention_cleanup.csv');

        $command = $this->get(InvoiceRetentionCleanupCommand::class);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('[DRY RUN]', $output);
        $this->assertStringContainsString('ORD-OLD-COMPLETED', $output);

        // Verify nothing was actually deleted
        $connectionPool = $this->get(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tx_products_domain_model_order');
        $count = $connection->count('uid', 'tx_products_domain_model_order', ['deleted' => 0]);
        $this->assertSame(3, $count);
    }

    #[Test]
    public function deletesOldTerminalOrdersAndKeepsNonTerminal(): void
    {
        $this->setUpProductsSite();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/invoice_retention_cleanup.csv');

        $command = $this->get(InvoiceRetentionCleanupCommand::class);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        $this->assertSame(0, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Deleted order ORD-OLD-COMPLETED', $output);
        $this->assertStringContainsString('Orders found: 1', $output);

        // Verify old COMPLETED order was deleted
        $connectionPool = $this->get(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tx_products_domain_model_order');

        $oldCompleted = $connection->select(['uid'], 'tx_products_domain_model_order', ['order_number' => 'ORD-OLD-COMPLETED', 'deleted' => 0])->fetchAssociative();
        $this->assertFalse($oldCompleted, 'Old COMPLETED order should be deleted');

        $recentCompleted = $connection->select(['uid'], 'tx_products_domain_model_order', ['order_number' => 'ORD-RECENT-COMPLETED', 'deleted' => 0])->fetchAssociative();
        $this->assertNotFalse($recentCompleted, 'Recent COMPLETED order should survive');

        $oldPending = $connection->select(['uid'], 'tx_products_domain_model_order', ['order_number' => 'ORD-OLD-PENDING', 'deleted' => 0])->fetchAssociative();
        $this->assertNotFalse($oldPending, 'Old PENDING order should survive (not terminal)');
    }

    #[Test]
    public function deletesRelatedItemsAndAddresses(): void
    {
        $this->setUpProductsSite();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/invoice_retention_cleanup.csv');

        $command = $this->get(InvoiceRetentionCleanupCommand::class);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        // Verify that order item for deleted order is also deleted
        $connectionPool = $this->get(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tx_products_domain_model_orderitem');

        $oldOrderItem = $connection->select(['uid'], 'tx_products_domain_model_orderitem', ['parent_order' => 1, 'deleted' => 0])->fetchAssociative();
        $this->assertFalse($oldOrderItem, 'OrderItem from deleted order should be hard-deleted');

        // Verify that address for deleted order is also deleted
        $addressConnection = $connectionPool->getConnectionForTable('tx_products_domain_model_orderaddress');
        $oldAddress = $addressConnection->select(['uid'], 'tx_products_domain_model_orderaddress', ['uid' => 1, 'deleted' => 0])->fetchAssociative();
        $this->assertFalse($oldAddress, 'OrderAddress from deleted order should be hard-deleted');

        // Verify that addresses for surviving orders still exist
        $recentAddress = $addressConnection->select(['uid'], 'tx_products_domain_model_orderaddress', ['uid' => 2, 'deleted' => 0])->fetchAssociative();
        $this->assertNotFalse($recentAddress, 'Address for recent order should survive');

        $pendingAddress = $addressConnection->select(['uid'], 'tx_products_domain_model_orderaddress', ['uid' => 3, 'deleted' => 0])->fetchAssociative();
        $this->assertNotFalse($pendingAddress, 'Address for pending order should survive');
    }

    /**
     * A payment transaction keeps the gateway's raw payload, and the order's terms document is a
     * file reference. Neither may outlive the order it belongs to.
     */
    #[Test]
    public function deletesPaymentTransactionsAndFileReferencesOfDeletedOrders(): void
    {
        $this->setUpProductsSite();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/invoice_retention_cleanup.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/invoice_retention_cleanup_related.csv');
        $command = $this->get(InvoiceRetentionCleanupCommand::class);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $connectionPool = $this->get(ConnectionPool::class);
        $transactions = $connectionPool->getConnectionForTable('tx_products_domain_model_paymenttransaction')
            ->select(['order_uid'], 'tx_products_domain_model_paymenttransaction')
            ->fetchFirstColumn();
        $this->assertSame([2], array_map('intval', $transactions));
        $references = $connectionPool->getConnectionForTable('sys_file_reference')
            ->select(['uid_foreign'], 'sys_file_reference', ['tablenames' => 'tx_products_domain_model_order'])
            ->fetchFirstColumn();
        $this->assertSame([2], array_map('intval', $references));
    }

    /**
     * The command iterates SiteFinder::getAllSites() and filters orders by site_identifier -
     * without an actual configured site, that loop runs zero times regardless of fixture
     * content. The fixture's site_identifier column uses "products" to match this.
     */
    private function setUpProductsSite(): void
    {
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
            [
                $this->buildDefaultLanguageConfiguration('en', '/'),
            ]
        );
    }
}
