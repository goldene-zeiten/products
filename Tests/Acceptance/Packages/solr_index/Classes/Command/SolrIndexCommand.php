<?php

declare(strict_types=1);

namespace GoldeneZeiten\ProductsSolrIndexTest\Command;

use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueInitializationService;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Fills EXT:solr's index queue for the acceptance site and indexes it into the live Solr server.
 *
 * EXT:solr ships no console command for this - indexing is only wired as a scheduler task
 * (IndexQueueWorkerTask). This command reproduces exactly that task's flow (queue initialization
 * plus IndexService::indexItems()) so the Playwright acceptance suite can seed Solr headlessly.
 * Installed only for the Solr acceptance combination.
 */
#[AsCommand('solr:index', 'Initialize and index the EXT:solr queue for the acceptance site into Solr')]
final class SolrIndexCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // The demo products are seeded via testing-framework's DataSet::import(), which writes rows with a
        // raw INSERT and therefore leaves "tstamp" at its column default of 0. EXT:solr copies that value
        // into the queue item's "changed" column, and an item is only "pending" (and thus indexed) while
        // its record changed later than it was last indexed - with changed = 0 every item counts as already
        // up to date and nothing is ever sent to Solr. Stamp the products with the current time so the queue
        // actually has work to do. (A real editor-created record always has a real tstamp; only this
        // fixture-seeded instance needs the nudge.)
        $productConnection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_products_domain_model_product');
        $productConnection->executeStatement(
            'UPDATE tx_products_domain_model_product SET tstamp = ' . time() . ' WHERE deleted = 0'
        );

        // SiteRepository, QueueInitializationService and Queue are not public DI services; EXT:solr's
        // own IndexQueueModuleController/IndexQueueWorkerTask obtain them via makeInstance, so do the same.
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getSiteByPageId(1);
        if ($site === null) {
            $output->writeln('<error>No EXT:solr site found for root page 1.</error>');
            return Command::FAILURE;
        }

        // Populate the index queue from this add-on's "products" indexing configuration. Only "products"
        // is initialized (not "*"): the acceptance suite asserts product search, and EXT:solr's "pages"
        // configuration would additionally trigger a full-frontend PageIndexer sub-request per page,
        // which is neither needed nor reliable to drive headlessly from the CLI.
        $queueInitializationService = GeneralUtility::makeInstance(QueueInitializationService::class);
        $queueInitializationService->initializeBySiteAndIndexConfiguration($site, 'products');

        $queue = GeneralUtility::makeInstance(Queue::class);
        $pending = $queue->getStatisticsBySite($site)->getPendingCount();
        $output->writeln(sprintf('Index queue initialized: %d item(s) pending.', $pending));
        if ($pending === 0) {
            $output->writeln('<error>Index queue is empty - nothing to index.</error>');
            return Command::FAILURE;
        }

        // Work the queue exactly like IndexQueueWorkerTask does (getInitializedIndexService()->indexItems()),
        // batching until nothing is pending. Terminate on progress, not merely on "queue reported done":
        // if a batch fails to reduce the pending count the queue is stuck, so bail rather than spin.
        $indexService = GeneralUtility::makeInstance(IndexService::class, $site);
        $iteration = 0;
        while ($pending > 0 && $iteration < 100) {
            $iteration++;
            $indexService->indexItems(50);
            $remaining = $queue->getStatisticsBySite($site)->getPendingCount();
            $output->writeln(sprintf('Batch %d indexed, %d item(s) still pending.', $iteration, $remaining));
            if ($remaining >= $pending) {
                $output->writeln('<error>Index queue made no progress; aborting to avoid an endless loop.</error>');
                return Command::FAILURE;
            }
            $pending = $remaining;
        }

        $failed = $queue->getStatisticsBySite($site)->getFailedCount();
        if ($failed > 0) {
            $output->writeln(sprintf('<error>%d queue item(s) failed to index.</error>', $failed));
            return Command::FAILURE;
        }

        $output->writeln('<info>Solr indexing complete.</info>');
        return Command::SUCCESS;
    }
}
