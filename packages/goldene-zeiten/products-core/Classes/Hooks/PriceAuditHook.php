<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Hooks;

use GoldeneZeiten\Products\Core\Domain\Validation\PricePeriodOverlapGuard;
use GoldeneZeiten\Products\Core\Service\PriceHistory\PriceHistoryRecorder;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\SysLog\Action\Database as SystemLogDatabaseAction;
use TYPO3\CMS\Core\SysLog\Error as SystemLogErrorClassification;

/**
 * Enforces price-period validity-window non-overlap (per audience scope) and auto-captures a
 * price-history audit entry whenever a base product/article price is edited directly, or a public
 * price period is saved.
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

    public function __construct(
        private readonly PricePeriodOverlapGuard $overlapGuard,
        private readonly PriceHistoryRecorder $priceHistoryRecorder,
    ) {}

    /**
     * @param array<string, mixed> $incomingFieldArray
     */
    public function processDatamap_preProcessFieldArray(array &$incomingFieldArray, string $table, int|string $id, DataHandler $dataHandler): void
    {
        if ($table === self::TABLE_PRICEPERIOD) {
            $effectiveRow = $this->overlapGuard->assertNoOverlap($incomingFieldArray, $id);
            $priceRelevantFieldsChanged = array_key_exists('price', $incomingFieldArray)
                || array_key_exists('valid_from', $incomingFieldArray)
                || array_key_exists('valid_until', $incomingFieldArray);
            if ($priceRelevantFieldsChanged && (int)($effectiveRow['fe_group'] ?? 0) === 0) {
                $this->priceHistoryRecorder->capturePeriodSave($effectiveRow);
            }
            return;
        }
        if (in_array($table, [self::TABLE_PRODUCT, self::TABLE_ARTICLE], true) && array_key_exists('price', $incomingFieldArray)) {
            $this->priceHistoryRecorder->captureDirectPriceEdit($table, $id, $incomingFieldArray);
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
}
