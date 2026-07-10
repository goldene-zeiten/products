<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Backend;

use Doctrine\DBAL\ParameterType;
use GoldeneZeiten\Products\Backend\Exception\ProductArchiveFailedException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Recurring housekeeping action for the "Products" backend module: moves products/articles
 * older than a given age (by crdate) out of the storage folder into an archive page, mirroring
 * legacy tt_products' MoveItemsWizardModuleFunctionController/MoveItemsUtility.
 */
final class ProductArchiveService
{
    private const TABLE_PRODUCT = 'tx_products_domain_model_product';
    private const TABLE_ARTICLE = 'tx_products_domain_model_article';

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array<string, int> moved-row count per table; a table without matches is omitted
     */
    public function archive(int $sourcePid, int $destinationPid, int $ageDays): array
    {
        if ($sourcePid <= 0 || $destinationPid <= 0 || $ageDays < 0) {
            return [];
        }

        $cutoff = time() - $ageDays * 86400;
        $cmd = [];
        $counts = [];
        foreach ([self::TABLE_PRODUCT, self::TABLE_ARTICLE] as $table) {
            $uids = $this->fetchEligibleUids($table, $sourcePid, $cutoff);
            if ($uids === []) {
                continue;
            }
            foreach ($uids as $uid) {
                $cmd[$table][$uid]['move'] = $destinationPid;
            }
            $counts[$table] = count($uids);
        }

        if ($cmd !== []) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start([], $cmd);
            $dataHandler->process_cmdmap();
            if ($dataHandler->errorLog !== []) {
                throw new ProductArchiveFailedException(implode(' ', $dataHandler->errorLog), 1783674644);
            }
        }

        return $counts;
    }

    /**
     * @return int[]
     */
    private function fetchEligibleUids(string $table, int $pid, int $cutoff): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $rows = $queryBuilder->select('uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, ParameterType::INTEGER)),
                $queryBuilder->expr()->lt('crdate', $queryBuilder->createNamedParameter($cutoff, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchFirstColumn();
        return array_map(intval(...), $rows);
    }
}
