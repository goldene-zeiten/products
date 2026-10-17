<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\OrderMailService;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\TestMailer;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * A site changes the header, footer or the whole frame of the order mails by overriding the shared layout or its
 * partials, without copying the mail templates.
 */
final class OrderMailLayoutTest extends AbstractFrontendTestCase
{
    private const FIXTURE_PATH = 'EXT:products_core/Tests/Functional/Service/Fixtures/OrderMailLayoutTest/';

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
    }

    #[Test]
    public function aSiteFooterPartialIsUsedAndKnowsTheTrader(): void
    {
        $this->writeSite([
            'partialRootPaths' => [
                'EXT:products_core/Resources/Private/Partials/',
                self::FIXTURE_PATH . 'Partials/',
            ],
        ], ['name' => 'Example Trader GmbH']);

        $this->get(OrderMailService::class)->sendOrderConfirmation($this->fetchOrder());

        $email = TestMailer::getSentEmails()[0];
        $this->assertStringContainsString('SITE-FOOTER Example Trader GmbH', (string)$email->getHtmlBody());
        $this->assertStringContainsString('SITE-FOOTER Example Trader GmbH', (string)$email->getTextBody());
    }

    #[Test]
    public function aSiteLayoutFramesEveryOrderMail(): void
    {
        $this->writeSite([
            'layoutRootPaths' => [
                'EXT:products_core/Resources/Private/Layouts/',
                self::FIXTURE_PATH . 'Layouts/',
            ],
        ]);

        $this->get(OrderMailService::class)->sendMerchantNotification($this->fetchOrder());

        $email = TestMailer::getSentEmails()[0];
        $this->assertStringContainsString('SITE-LAYOUT-START', (string)$email->getHtmlBody());
        $this->assertStringContainsString('SITE-LAYOUT-END', (string)$email->getTextBody());
    }

    #[Test]
    public function theDefaultFooterPrintsTheShopsVatId(): void
    {
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
                'settings' => ['products' => [
                    'email' => ['merchantRecipient' => 'merchant@example.com'],
                    'invoice' => ['vatId' => 'DE987654321'],
                ]],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $this->get(OrderMailService::class)->sendOrderConfirmation($this->fetchOrder());

        $email = TestMailer::getSentEmails()[0];
        $this->assertStringContainsString('DE987654321', (string)$email->getHtmlBody());
        $this->assertStringContainsString('DE987654321', (string)$email->getTextBody());
    }

    /**
     * @param array<string, mixed> $email
     * @param array<string, string> $trader
     */
    private function writeSite(array $email, array $trader = []): void
    {
        $email['merchantRecipient'] = 'merchant@example.com';
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
                'settings' => ['products' => ['email' => $email, 'withdrawal' => ['trader' => $trader]]],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
