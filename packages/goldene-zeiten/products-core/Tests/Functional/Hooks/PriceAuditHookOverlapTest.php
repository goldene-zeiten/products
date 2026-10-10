<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Hooks;

use GoldeneZeiten\Products\Core\Domain\Validation\PricePeriodOverlapGuard;
use GoldeneZeiten\Products\Core\Exception\PricePeriodOverlapException;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Tests PricePeriodOverlapGuard integration via PriceAuditHook.
 * Verifies that overlapping price periods are rejected based on fe_group scope.
 */
final class PriceAuditHookOverlapTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PriceAuditHookOverlapTest/price_periods.csv');
    }

    #[Test]
    public function overlappingPublicPeriodForSameProductIsRejected(): void
    {
        $this->expectException(PricePeriodOverlapException::class);

        $this->get(PricePeriodOverlapGuard::class)->assertNoOverlap(
            [
                'product' => 1,
                'article' => 0,
                'fe_group' => 0,
                'valid_from' => 960000000,
                'valid_until' => 990000000,
            ],
            'NEW123'
        );
    }

    #[Test]
    public function overlappingWindowInDifferentFeGroupIsAllowed(): void
    {
        $result = $this->get(PricePeriodOverlapGuard::class)->assertNoOverlap(
            [
                'product' => 1,
                'article' => 0,
                'fe_group' => 2,
                'valid_from' => 960000000,
                'valid_until' => 990000000,
            ],
            'NEW456'
        );

        $this->assertSame(2, (int)$result['fe_group']);
    }

    #[Test]
    public function overlappingWindowInSameFeGroupAsExistingResellerPeriodIsRejected(): void
    {
        $this->expectException(PricePeriodOverlapException::class);

        $this->get(PricePeriodOverlapGuard::class)->assertNoOverlap(
            [
                'product' => 1,
                'article' => 0,
                'fe_group' => 1,
                'valid_from' => 960000000,
                'valid_until' => 990000000,
            ],
            'NEW789'
        );
    }

    #[Test]
    public function nonOverlappingWindowInSameScopeIsAllowed(): void
    {
        $result = $this->get(PricePeriodOverlapGuard::class)->assertNoOverlap(
            [
                'product' => 1,
                'article' => 0,
                'fe_group' => 0,
                'valid_from' => 978307201,
                'valid_until' => 1000000000,
            ],
            'NEW999'
        );

        $this->assertSame(1, (int)$result['product']);
    }

    /**
     * FormEngine submits datetime fields as ISO 8601 strings, which DataHandler converts only after the
     * hook ran. The overlap check must read them as dates, not cast "2000-06-01T..." to 2000.
     */
    #[Test]
    public function anOverlappingPeriodSubmittedWithIsoDatesIsRejected(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'tx_products_domain_model_priceperiod' => [
                'NEW1' => [
                    'pid' => 2,
                    'product' => 1,
                    'fe_group' => 0,
                    'price' => '7.00',
                    'valid_from' => '2000-06-01T00:00:00Z',
                    'valid_until' => '2000-07-01T00:00:00Z',
                ],
            ],
        ], []);

        $this->expectException(PricePeriodOverlapException::class);
        $this->expectExceptionCode(1751900000);

        $dataHandler->process_datamap();
    }

    /**
     * A period added inline in the product form carries no product uid of its own yet; the product it
     * belongs to is only known from the product's inline field in the same datamap.
     */
    #[Test]
    public function anOverlappingInlinePeriodOfAnExistingProductIsRejected(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'tx_products_domain_model_product' => [
                1 => [
                    'price_periods' => '10,11,NEW1',
                ],
            ],
            'tx_products_domain_model_priceperiod' => [
                'NEW1' => [
                    'pid' => 2,
                    'fe_group' => 0,
                    'price' => '7.00',
                    'valid_from' => '2000-06-01T00:00:00Z',
                    'valid_until' => '2000-07-01T00:00:00Z',
                ],
            ],
        ], []);

        $this->expectException(PricePeriodOverlapException::class);
        $this->expectExceptionCode(1751900000);

        $dataHandler->process_datamap();
    }

    #[Test]
    public function aSavedPublicInlinePeriodIsRecordedInThePriceHistoryWithItsProductAndDates(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'tx_products_domain_model_product' => [
                1 => [
                    'price_periods' => '10,11,NEW1',
                ],
            ],
            'tx_products_domain_model_priceperiod' => [
                'NEW1' => [
                    'pid' => 2,
                    'fe_group' => 0,
                    'price' => '7.00',
                    'valid_from' => '2026-11-01T00:00:00Z',
                    'valid_until' => '2026-12-01T00:00:00Z',
                ],
            ],
        ], []);
        $dataHandler->process_datamap();

        $this->assertSame([], $dataHandler->errorLog);
        $period = $this->getAllRecords('tx_products_domain_model_priceperiod', true)[(int)$dataHandler->substNEWwithIDs['NEW1']] ?? [];
        $history = $this->getAllRecords('tx_products_domain_model_pricehistoryentry');
        $this->assertCount(1, $history);
        $this->assertSame(1, (int)$history[0]['product']);
        $this->assertSame('7.00', number_format((float)$history[0]['price'], 2, '.', ''));
        $this->assertSame((int)($period['valid_from'] ?? -1), (int)$history[0]['valid_from']);
        $this->assertSame((int)($period['valid_until'] ?? -1), (int)$history[0]['valid_until']);
        $this->assertGreaterThan(1790000000, (int)$history[0]['valid_from']);
    }
}
