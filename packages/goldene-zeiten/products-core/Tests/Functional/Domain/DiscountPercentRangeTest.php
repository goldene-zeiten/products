<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Domain;

use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class DiscountPercentRangeTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DiscountPercentRangeTest/records.csv');
    }

    #[Test]
    #[DataProvider('discountFieldDataProvider')]
    public function aDiscountPercentIsKeptBetweenZeroAndHundred(string $table, string $field, string $submitted, float $expected): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                $table => [
                    1 => [
                        $field => $submitted,
                    ],
                ],
            ],
            []
        );

        $dataHandler->process_datamap();

        $this->assertSame($expected, (float)$this->getAllRecords($table, true)[1][$field]);
    }

    public static function discountFieldDataProvider(): \Generator
    {
        foreach (
            [
                'product' => ['tx_products_domain_model_product', 'discount_percent'],
                'category' => ['tx_products_domain_model_category', 'discount_percent'],
                'frontend user' => ['fe_users', 'tx_products_discount_percent'],
                'frontend user group' => ['fe_groups', 'tx_products_discount_percent'],
            ] as $label => [$table, $field]
        ) {
            yield $label . ' above 100' => [
                'table' => $table,
                'field' => $field,
                'submitted' => '150',
                'expected' => 100.0,
            ];
            yield $label . ' below 0' => [
                'table' => $table,
                'field' => $field,
                'submitted' => '-20',
                'expected' => 0.0,
            ];
            yield $label . ' within range' => [
                'table' => $table,
                'field' => $field,
                'submitted' => '12.5',
                'expected' => 12.5,
            ];
        }
    }
}
