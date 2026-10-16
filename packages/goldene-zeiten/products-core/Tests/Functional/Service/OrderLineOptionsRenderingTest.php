<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Invoice\InvoiceRenderer;
use GoldeneZeiten\Products\Core\Service\OrderMailService;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\TestMailer;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * What was engraved, in which size, is part of what the customer ordered and what the merchant produces,
 * so every document listing the order lines lists their options.
 */
final class OrderLineOptionsRenderingTest extends AbstractFrontendTestCase
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
    ];

    protected function setUp(): void
    {
        parent::setUp();
        TestMailer::reset();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderLineOptionsRenderingTest/order_with_line_options.csv');
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
    #[DataProvider('mailProvider')]
    public function orderMailsListTheOptionsOfEachLine(string $method): void
    {
        $this->get(OrderMailService::class)->{$method}($this->fetchOrder());

        $email = TestMailer::getSentEmails()[0];
        $this->assertStringContainsString('Engraving: Anna &amp; Ben', (string)$email->getHtmlBody());
        $this->assertStringContainsString("11,90\u{00A0}€", (string)$email->getHtmlBody());
        $this->assertStringContainsString('Ring size: 54', (string)$email->getHtmlBody());
        $this->assertMatchesRegularExpression(
            "/Engagement Ring \\(Gold 585\\): [^\\n]+\\n   Engraving: Anna & Ben \\(\\+ 11,90\u{00A0}€\\)\\n   Ring size: 54\\n/u",
            (string)$email->getTextBody()
        );
    }

    public static function mailProvider(): \Generator
    {
        yield 'order confirmation' => [
            'method' => 'sendOrderConfirmation',
        ];
        yield 'merchant notification' => [
            'method' => 'sendMerchantNotification',
        ];
    }

    #[Test]
    public function theInvoiceListsTheOptionsOfEachLine(): void
    {
        $html = $this->get(InvoiceRenderer::class)->render($this->fetchOrder());

        $this->assertStringContainsString('Engraving: Anna &amp; Ben', $html);
        $this->assertStringContainsString('Ring size: 54', $html);
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
