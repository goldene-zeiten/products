<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Offline\Tests\Functional\Payment;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Payment\Offline\Payment\PrepaymentPaymentMethod;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;

/**
 * A prepayment order only ships once the money arrives, so the confirmation tells the customer what to
 * transfer where, in the language of the shop.
 */
final class PrepaymentInstructionsTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'de' => [
            'id' => 0,
            'title' => 'Deutsch',
            'locale' => 'de_DE.UTF-8',
        ],
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-payment-offline',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/order.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'settings' => [
                    'products' => [
                        'payment' => [
                            'prepayment' => [
                                'accountHolder' => 'Goldschmiede Muster',
                                'iban' => 'DE02 1203 0000 0000 2020 51',
                                'bic' => 'BYLADEM1001',
                                'bankName' => 'Musterbank',
                            ],
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('de', '/')]
        );
    }

    #[Test]
    #[DataProvider('instructionProvider')]
    public function theInstructionsNameAmountAccountAndReference(string $expectedContent): void
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);

        $instructions = $this->get(PrepaymentPaymentMethod::class)->getPaymentInstructions($order);

        $this->assertStringContainsString($expectedContent, $instructions);
    }

    public static function instructionProvider(): \Generator
    {
        yield 'amount in the shop locale' => [
            'expectedContent' => "1.052,50\u{00A0}€",
        ];
        yield 'account holder' => [
            'expectedContent' => 'Goldschmiede Muster',
        ];
        yield 'IBAN' => [
            'expectedContent' => 'IBAN DE02 1203 0000 0000 2020 51',
        ];
        yield 'BIC' => [
            'expectedContent' => 'BIC BYLADEM1001',
        ];
        yield 'order number as reference' => [
            'expectedContent' => 'ORD-1042',
        ];
        yield 'German text' => [
            'expectedContent' => 'Bitte überweisen Sie',
        ];
    }
}
