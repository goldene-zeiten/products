<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\OrderMailService;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\TestMailer;
use GoldeneZeiten\Products\EventFixture\OrderMailListener;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * The order confirmation is the customer's contract confirmation and the merchant notification is what the
 * order is fulfilled from, so both carry the complete order.
 */
final class OrderMailContentTest extends AbstractFrontendTestCase
{
    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
        'de' => [
            'id' => 0,
            'title' => 'Deutsch',
            'locale' => 'de_DE.UTF-8',
        ],
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-payment-fixture',
        'goldene-zeiten/products-event-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        TestMailer::reset();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderMailContentTest/complete_order.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['merchantRecipient' => 'merchant@example.com'],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('de', '/')]
        );
    }

    #[Test]
    #[DataProvider('orderContentProvider')]
    public function orderConfirmationCarriesTheCompleteOrder(string $expectedContent): void
    {
        $subject = $this->get(OrderMailService::class);

        $subject->sendOrderConfirmation($this->fetchOrder());

        $email = TestMailer::getSentEmails()[0];
        $this->assertStringContainsString($expectedContent, (string)$email->getHtmlBody());
        $this->assertStringContainsString($expectedContent, (string)$email->getTextBody());
    }

    #[Test]
    #[DataProvider('orderContentProvider')]
    public function merchantNotificationCarriesTheCompleteOrder(string $expectedContent): void
    {
        $subject = $this->get(OrderMailService::class);

        $subject->sendMerchantNotification($this->fetchOrder());

        $email = TestMailer::getSentEmails()[0];
        $this->assertStringContainsString($expectedContent, (string)$email->getHtmlBody());
        $this->assertStringContainsString($expectedContent, (string)$email->getTextBody());
    }

    public static function orderContentProvider(): \Generator
    {
        yield 'item title' => [
            'expectedContent' => 'Engagement Ring',
        ];
        yield 'article title' => [
            'expectedContent' => 'Gold 585',
        ];
        yield 'line total in the order locale' => [
            'expectedContent' => "995,00\u{00A0}€",
        ];
        yield 'grand total in the order locale' => [
            'expectedContent' => "1.052,50\u{00A0}€",
        ];
        yield 'VAT line' => [
            'expectedContent' => "168,05\u{00A0}€",
        ];
        yield 'billing company' => [
            'expectedContent' => 'Goldschmiede Muster',
        ];
        yield 'billing telephone' => [
            'expectedContent' => '+49 30 123456',
        ];
        yield 'billing VAT id' => [
            'expectedContent' => 'DE123456789',
        ];
        yield 'delivery address' => [
            'expectedContent' => 'Harbour Road 7',
        ];
        yield 'shipping method' => [
            'expectedContent' => 'Express delivery',
        ];
        yield 'payment method' => [
            'expectedContent' => 'Surcharge Fixture Payment',
        ];
        yield 'translated salutation' => [
            'expectedContent' => 'Frau Jane Shopper',
        ];
        yield 'desired delivery date' => [
            'expectedContent' => '23.12.2030',
        ];
        yield 'customer note' => [
            'expectedContent' => 'Please wrap it as a gift.',
        ];
    }

    #[Test]
    public function moneyIsNeverPrintedAsARawDecimalString(): void
    {
        $subject = $this->get(OrderMailService::class);

        $subject->sendOrderConfirmation($this->fetchOrder());

        $email = TestMailer::getSentEmails()[0];
        $this->assertStringNotContainsString('1052.50 EUR', (string)$email->getHtmlBody());
        $this->assertStringNotContainsString('1052.50 EUR', (string)$email->getTextBody());
    }

    #[Test]
    public function paymentInstructionsOfThePaymentMethodAreShown(): void
    {
        $order = $this->fetchOrder();
        $order->setPaymentMethod('fixture-instructions');
        $subject = $this->get(OrderMailService::class);

        $subject->sendOrderConfirmation($order);

        $email = TestMailer::getSentEmails()[0];
        $this->assertStringContainsString('Transfer the total to IBAN DE00 1234 5678, reference ORD-77.', (string)$email->getHtmlBody());
        $this->assertStringContainsString('Transfer the total to IBAN DE00 1234 5678, reference ORD-77.', (string)$email->getTextBody());
    }

    #[Test]
    public function aPaymentMethodThatIsNoLongerInstalledIsShownByItsIdentifier(): void
    {
        $order = $this->fetchOrder();
        $order->setPaymentMethod('legacy-gateway');
        $subject = $this->get(OrderMailService::class);

        $subject->sendOrderConfirmation($order);

        $this->assertStringContainsString('legacy-gateway', (string)TestMailer::getSentEmails()[0]->getTextBody());
    }

    #[Test]
    public function listenersCanAdjustEveryOrderMailBeforeItIsSent(): void
    {
        OrderMailListener::$enabled = true;
        OrderMailListener::$templates = [];
        $subject = $this->get(OrderMailService::class);

        $subject->sendOrderConfirmation($this->fetchOrder());
        $subject->sendMerchantNotification($this->fetchOrder());

        $this->assertSame(['OrderConfirmation', 'MerchantNotification'], OrderMailListener::$templates);
        $this->assertSame('archive@example.com', TestMailer::getSentEmails()[0]->getCc()[0]->getAddress());
    }

    protected function tearDown(): void
    {
        OrderMailListener::$enabled = false;
        OrderMailListener::$templates = [];
        parent::tearDown();
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
