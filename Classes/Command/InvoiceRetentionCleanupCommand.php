<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Command;

use GoldeneZeiten\Products\Domain\Enum\OrderStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\SiteFinder;

#[AsCommand(
    name: 'products:cleanup-invoice-retention',
    description: 'Hard-deletes orders (and their items/addresses) past the configured GDPR retention period, for terminal (COMPLETED/CANCELLED) orders only.'
)]
final class InvoiceRetentionCleanupCommand extends Command
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Report what would be deleted without committing'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = $input->getOption('dry-run');
        $totalFound = 0;
        $totalDeleted = 0;

        foreach ($this->siteFinder->getAllSites() as $site) {
            $retentionDays = (int)$site->getSettings()->get('products.gdpr.invoiceRetentionDays', 3650);
            $cutoffTimestamp = time() - ($retentionDays * 86400);
            $siteIdentifier = $site->getIdentifier();

            // Query orders that match criteria: old enough, terminal status, not deleted, this site
            $connection = $this->connectionPool->getConnectionForTable('tx_products_domain_model_order');
            $queryBuilder = $connection->createQueryBuilder();
            $rows = $queryBuilder
                ->select('uid', 'order_number', 'order_date')
                ->from('tx_products_domain_model_order')
                ->where(
                    $queryBuilder->expr()->lt('order_date', $cutoffTimestamp),
                    $queryBuilder->expr()->in('status', [
                        $queryBuilder->createNamedParameter(OrderStatus::COMPLETED->value),
                        $queryBuilder->createNamedParameter(OrderStatus::CANCELLED->value),
                    ]),
                    $queryBuilder->expr()->eq('deleted', 0),
                    $queryBuilder->expr()->eq('site_identifier', $queryBuilder->createNamedParameter($siteIdentifier))
                )
                ->orderBy('uid', 'ASC')
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($rows as $row) {
                $totalFound++;
                $orderUid = (int)$row['uid'];
                $orderNumber = $row['order_number'];
                $orderDate = $row['order_date'];

                if ($isDryRun) {
                    $output->writeln(sprintf(
                        '[DRY RUN] Would delete order %s (date: %s, uid: %d)',
                        $orderNumber,
                        date('Y-m-d H:i:s', (int)$orderDate),
                        $orderUid
                    ));
                } else {
                    $this->deleteOrder($connection, $orderUid);
                    $totalDeleted++;
                    $output->writeln(sprintf(
                        'Deleted order %s (date: %s, uid: %d)',
                        $orderNumber,
                        date('Y-m-d H:i:s', (int)$orderDate),
                        $orderUid
                    ));
                }
            }
        }

        $summaryMessage = sprintf(
            '%sOrders found: %d, deleted: %d',
            $isDryRun ? '[DRY RUN] ' : '',
            $totalFound,
            $isDryRun ? 0 : $totalDeleted
        );
        $output->writeln($summaryMessage);
        $this->logger->info($summaryMessage);

        return Command::SUCCESS;
    }

    private function deleteOrder(
        \TYPO3\CMS\Core\Database\Connection $connection,
        int $orderUid
    ): void {
        $connection->beginTransaction();
        try {
            // Delete order items first
            $connection->delete('tx_products_domain_model_orderitem', ['parent_order' => $orderUid]);

            // Fetch the order to get the address uids
            $queryBuilder = $connection->createQueryBuilder();
            $orderRow = $queryBuilder
                ->select('billing_address', 'delivery_address')
                ->from('tx_products_domain_model_order')
                ->where($queryBuilder->expr()->eq('uid', $orderUid))
                ->executeQuery()
                ->fetchAssociative();

            if ($orderRow) {
                // Delete billing address if it exists and is not null/0
                $billingAddressUid = (int)($orderRow['billing_address'] ?? 0);
                if ($billingAddressUid > 0) {
                    $connection->delete('tx_products_domain_model_orderaddress', ['uid' => $billingAddressUid]);
                }

                // Delete delivery address if it exists and is not null/0
                $deliveryAddressUid = (int)($orderRow['delivery_address'] ?? 0);
                if ($deliveryAddressUid > 0) {
                    $connection->delete('tx_products_domain_model_orderaddress', ['uid' => $deliveryAddressUid]);
                }
            }

            // Delete the order itself
            $connection->delete('tx_products_domain_model_order', ['uid' => $orderUid]);

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
