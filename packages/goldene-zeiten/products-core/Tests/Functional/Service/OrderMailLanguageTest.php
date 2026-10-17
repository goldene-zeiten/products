<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\OrderMailService;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\TestMailer;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Order mails speak the language the order was placed in - also when they are sent outside a frontend request
 * of that language, e.g. after the return from a payment provider or from the backend.
 */
final class OrderMailLanguageTest extends AbstractFrontendTestCase
{
    protected const LANGUAGE_PRESETS = [
        'de' => [
            'id' => 0,
            'title' => 'Deutsch',
            'locale' => 'de_DE.UTF-8',
        ],
        'en' => [
            'id' => 1,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-payment-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        TestMailer::reset();
        unset($GLOBALS['TYPO3_REQUEST']);
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
            [$this->buildDefaultLanguageConfiguration('de', '/'), $this->buildLanguageConfiguration('en', '/en/')]
        );
    }

    #[Test]
    #[DataProvider('languageProvider')]
    public function theConfirmationSpeaksTheLanguageOfTheOrder(int $languageId, string $subject, string $body): void
    {
        $subjectService = $this->get(OrderMailService::class);

        $subjectService->sendOrderConfirmation($this->fetchOrder($languageId));

        $email = TestMailer::getSentEmails()[0];
        $this->assertSame($subject, $email->getSubject());
        $this->assertStringContainsString($body, (string)$email->getTextBody());
        $this->assertStringContainsString($body, (string)$email->getHtmlBody());
    }

    public static function languageProvider(): \Generator
    {
        yield 'the default language' => [
            'languageId' => 0,
            'subject' => 'Bestellbestätigung: ORD-77',
            'body' => 'vielen Dank für Ihre Bestellung ORD-77.',
        ];
        yield 'a translation' => [
            'languageId' => 1,
            'subject' => 'Order Confirmation: ORD-77',
            'body' => 'thank you for your order ORD-77.',
        ];
    }

    #[Test]
    public function theMerchantNotificationAndTheStatusMailSpeakTheLanguageOfTheOrder(): void
    {
        $subject = $this->get(OrderMailService::class);

        $subject->sendMerchantNotification($this->fetchOrder(0));
        $subject->sendOrderStatusChanged($this->fetchOrder(0), OrderStatus::CONFIRMED, OrderStatus::SHIPPED);

        $emails = TestMailer::getSentEmails();
        $this->assertSame('Neue Bestellung: ORD-77', $emails[0]->getSubject());
        $this->assertSame('Bestellstatus aktualisiert: ORD-77', $emails[1]->getSubject());
        $this->assertStringContainsString('Versendet', (string)$emails[1]->getTextBody());
    }

    #[Test]
    public function theWithdrawalInstructionsSpeakTheLanguageOfTheOrder(): void
    {
        $subject = $this->get(OrderMailService::class);

        $subject->sendOrderConfirmation($this->fetchOrder(0));

        $body = (string)TestMailer::getSentEmails()[0]->getTextBody();
        $this->assertStringContainsString('Muster-Widerrufsbelehrung', $body);
        $this->assertStringNotContainsString('Model instructions on withdrawal', $body);
    }

    private function fetchOrder(int $languageId): Order
    {
        $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_order')
            ->update('tx_products_domain_model_order', ['language_id' => $languageId], ['uid' => 1]);
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
