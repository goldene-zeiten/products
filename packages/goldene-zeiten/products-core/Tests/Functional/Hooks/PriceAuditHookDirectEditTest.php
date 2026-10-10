<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Hooks;

use GoldeneZeiten\Products\Core\Hooks\PriceAuditHook;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Tests PriceAuditHook integration with PriceHistoryRecorder.
 * Verifies that direct price edits and delete commands are handled correctly.
 */
final class PriceAuditHookDirectEditTest extends AbstractFunctionalTestCase
{
    private const TABLE_PRODUCT = 'tx_products_domain_model_product';
    private const TABLE_PRICEHISTORYENTRY = 'tx_products_domain_model_pricehistoryentry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PriceAuditHookDirectEditTest/products.csv');
    }

    #[Test]
    public function directPriceEditCapturesTheOldPriceInHistory(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([self::TABLE_PRODUCT => [1 => ['price' => '24.99']]], []);
        $dataHandler->process_datamap();

        $rows = $this->getAllRecords(self::TABLE_PRICEHISTORYENTRY);
        $this->assertCount(1, $rows);
        $this->assertSame(1, (int)$rows[0]['product']);
        $this->assertSame('19.99', $rows[0]['price']);
    }

    #[Test]
    public function resavingTheSamePriceDoesNotCreateADuplicateHistoryRow(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([self::TABLE_PRODUCT => [1 => ['price' => '19.99']]], []);
        $dataHandler->process_datamap();

        $rows = $this->getAllRecords(self::TABLE_PRICEHISTORYENTRY);
        $this->assertCount(0, $rows);
    }

    #[Test]
    public function newRecordPlaceholderIdDoesNotCreateAHistoryRow(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([self::TABLE_PRODUCT => ['NEW1a2b3c4d' => ['pid' => 2, 'title' => 'New Product', 'price' => '9.99']]], []);
        $dataHandler->process_datamap();

        $this->assertArrayHasKey('NEW1a2b3c4d', $dataHandler->substNEWwithIDs);
        $rows = $this->getAllRecords(self::TABLE_PRICEHISTORYENTRY);
        $this->assertCount(0, $rows);
    }

    /**
     * A price edit DataHandler refuses never reached the product, so it must not reach the legal
     * price history either.
     */
    #[Test]
    public function aDeniedPriceEditLeavesNoHistoryRow(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PriceAuditHookDirectEditTest/editor_without_product_access.csv');
        $backendUser = $this->setUpBackendUser(2);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([self::TABLE_PRODUCT => [1 => ['price' => '24.99']]], []);
        $dataHandler->process_datamap();

        $this->assertNotSame([], $dataHandler->errorLog);
        $this->assertCount(0, $this->getAllRecords(self::TABLE_PRICEHISTORYENTRY));
    }

    #[Test]
    public function deleteCommandAgainstAHistoryRecordIsDenied(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], []);
        $dataHandler->BE_USER = $backendUser;

        // Insert a history entry directly
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable(self::TABLE_PRICEHISTORYENTRY);
        $now = time();
        $connection->insert(self::TABLE_PRICEHISTORYENTRY, [
            'pid' => 2,
            'product' => 1,
            'price' => '19.99',
            'valid_from' => 0,
            'valid_until' => 0,
            'tstamp' => $now,
            'crdate' => $now,
            'recorded_at' => $now,
        ]);

        // Get the inserted record's uid
        $rows = $this->getAllRecords(self::TABLE_PRICEHISTORYENTRY);
        $this->assertCount(1, $rows);
        $historyUid = (int)$rows[0]['uid'];

        // Try to delete it - should be denied
        $commandIsProcessed = false;
        $this->get(PriceAuditHook::class)->processCmdmap(
            'delete',
            self::TABLE_PRICEHISTORYENTRY,
            $historyUid,
            1,
            $commandIsProcessed,
            $dataHandler,
            false
        );

        $this->assertTrue($commandIsProcessed, 'Delete command should have been processed (denied)');
    }
}
