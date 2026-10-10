<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Hooks;

use GoldeneZeiten\Products\Core\Domain\Validation\PricePeriodOverlapGuard;
use GoldeneZeiten\Products\Core\Service\PriceHistory\PriceHistoryRecorder;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\SysLog\Action\Database as SystemLogDatabaseAction;
use TYPO3\CMS\Core\SysLog\Error as SystemLogErrorClassification;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Enforces price-period validity-window non-overlap (per audience scope) before a period is written,
 * and records a price-history audit entry once a base product/article price edit or a public price
 * period has actually been written - never for an edit DataHandler refused.
 *
 * @internal Registered as a classic DataHandler hook in ext_localconf.php.
 *
 * Public, because TYPO3 instantiates a hook through makeInstance, which only injects dependencies
 * into a public service.
 */
#[Autoconfigure(public: true)]
final class PriceAuditHook
{
    private const TABLE_PRICEPERIOD = 'tx_products_domain_model_priceperiod';
    private const TABLE_PRODUCT = 'tx_products_domain_model_product';
    private const TABLE_ARTICLE = 'tx_products_domain_model_article';
    private const TABLE_PRICEHISTORYENTRY = 'tx_products_domain_model_pricehistoryentry';
    private const PERIOD_PARENTS = [
        self::TABLE_PRODUCT => 'product',
        self::TABLE_ARTICLE => 'article',
    ];

    /**
     * Prices before an accepted edit, per DataHandler run: [run][table][uid] => price.
     *
     * @var array<int, array<string, array<int, string>>>
     */
    private array $previousPrices = [];

    /**
     * Written period uids per DataHandler run, recorded once the run has linked inline periods.
     *
     * @var array<int, array<int, true>>
     */
    private array $writtenPeriods = [];

    public function __construct(
        private readonly PricePeriodOverlapGuard $overlapGuard,
        private readonly PriceHistoryRecorder $priceHistoryRecorder,
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @param array<string, mixed> $incomingFieldArray
     */
    public function processDatamap_preProcessFieldArray(array &$incomingFieldArray, string $table, int|string $id, DataHandler $dataHandler): void
    {
        if ($table === self::TABLE_PRICEPERIOD) {
            $this->overlapGuard->assertNoOverlap($this->withInlineParent($incomingFieldArray, $id, $dataHandler), $id);
        }
    }

    /**
     * Runs after DataHandler's access checks, with only the fields that actually change.
     *
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_postProcessFieldArray(string $status, string $table, int|string $id, array &$fieldArray, DataHandler $dataHandler): void
    {
        if ($status !== 'update' || !isset(self::PERIOD_PARENTS[$table]) || !array_key_exists('price', $fieldArray)) {
            return;
        }
        $previousPrice = $this->fetchColumn($table, (int)$id, 'price');
        if ($previousPrice !== null) {
            $this->previousPrices[spl_object_id($dataHandler)][$table][(int)$id] = $previousPrice;
        }
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_afterDatabaseOperations(string $status, string $table, int|string $id, array $fieldArray, DataHandler $dataHandler): void
    {
        $run = spl_object_id($dataHandler);
        if ($table === self::TABLE_PRICEPERIOD && array_intersect(['price', 'valid_from', 'valid_until'], array_keys($fieldArray)) !== []) {
            $uid = $status === 'new' ? (int)($dataHandler->substNEWwithIDs[$id] ?? 0) : (int)$id;
            $this->writtenPeriods[$run][$uid] = true;
            return;
        }
        $previousPrice = $this->previousPrices[$run][$table][(int)$id] ?? null;
        unset($this->previousPrices[$run][$table][(int)$id]);
        if ($previousPrice !== null && $this->fetchColumn($table, (int)$id, 'price') !== $previousPrice) {
            $this->priceHistoryRecorder->captureDirectPriceEdit($table, (int)$id, $previousPrice);
        }
    }

    /**
     * An inline period is linked to its product or article only at the end of the run.
     */
    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        $run = spl_object_id($dataHandler);
        $periodUids = array_keys($this->writtenPeriods[$run] ?? []);
        unset($this->writtenPeriods[$run], $this->previousPrices[$run]);
        foreach ($periodUids as $periodUid) {
            $period = $this->fetchPeriod($periodUid);
            if ($period !== null && (int)$period['fe_group'] === 0) {
                $this->priceHistoryRecorder->capturePeriodSave($period);
            }
        }
    }

    /**
     * Legal audit rows must never be deleted or edited through normal DataHandler commands, even
     * softly - deny both commands unconditionally against the price-history ledger table.
     *
     * @param mixed $value
     * @param mixed $pasteUpdate
     */
    public function processCmdmap(string $command, string $table, int|string $id, $value, bool &$commandIsProcessed, DataHandler $dataHandler, $pasteUpdate): void
    {
        if ($commandIsProcessed || $table !== self::TABLE_PRICEHISTORYENTRY) {
            return;
        }
        if (in_array($command, ['delete', 'edit'], true)) {
            $commandIsProcessed = true;
            $dataHandler->log(
                $table,
                is_int($id) ? $id : 0,
                SystemLogDatabaseAction::UPDATE,
                null,
                SystemLogErrorClassification::USER_ERROR,
                'Attempt to "{command}" a price-history audit record {table}:{uid} denied - this ledger is append-only',
                null,
                ['command' => $command, 'table' => $table, 'uid' => $id]
            );
        }
    }

    /**
     * A new inline period carries no parent uid yet; the parent is the existing product or article
     * whose inline field in the same datamap lists the period.
     *
     * @param array<string, mixed> $incomingFieldArray
     * @return array<string, mixed>
     */
    private function withInlineParent(array $incomingFieldArray, int|string $id, DataHandler $dataHandler): array
    {
        if (is_int($id) || array_key_exists('product', $incomingFieldArray) || array_key_exists('article', $incomingFieldArray)) {
            return $incomingFieldArray;
        }
        foreach (self::PERIOD_PARENTS as $parentTable => $parentColumn) {
            foreach ($dataHandler->datamap[$parentTable] ?? [] as $parentId => $parentFields) {
                $periodIds = GeneralUtility::trimExplode(',', (string)($parentFields['price_periods'] ?? ''), true);
                if (is_int($parentId) && in_array((string)$id, $periodIds, true)) {
                    $incomingFieldArray[$parentColumn] = $parentId;
                    return $incomingFieldArray;
                }
            }
        }
        return $incomingFieldArray;
    }

    private function fetchColumn(string $table, int $uid, string $column): ?string
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $value = $queryBuilder->select($column)
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
        return $value === false ? null : (string)$value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPeriod(int $uid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_PRICEPERIOD);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $row = $queryBuilder->select('product', 'article', 'fe_group', 'price', 'valid_from', 'valid_until')
            ->from(self::TABLE_PRICEPERIOD)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : $row;
    }
}
