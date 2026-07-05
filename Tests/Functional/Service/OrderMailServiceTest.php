<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\OrderMailService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use GoldeneZeiten\Products\Tests\Functional\Fixtures\TestMailer;

final class OrderMailServiceTest extends AbstractFrontendTestCase
{
    private OrderMailService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        TestMailer::reset();
        $this->subject = $this->get(OrderMailService::class);
    }

    /**
     * @test
     */
    public function sendOrderConfirmationSendsMailToCustomer(): void
    {
        $order = $this->buildOrder();

        $this->subject->sendOrderConfirmation($order);

        $sentEmails = TestMailer::getSentEmails();
        self::assertCount(1, $sentEmails);
        self::assertSame('shopper@example.com', $sentEmails[0]->getTo()[0]->getAddress());
        self::assertStringContainsString('ORD-1', (string)$sentEmails[0]->getSubject());
        self::assertStringContainsString('thank you for your order ORD-1', (string)$sentEmails[0]->getTextBody());
    }

    /**
     * @test
     */
    public function sendMerchantNotificationIsSkippedWithoutConfiguredRecipient(): void
    {
        $this->subject->sendMerchantNotification($this->buildOrder());

        self::assertCount(0, TestMailer::getSentEmails());
    }

    /**
     * @test
     */
    public function sendMerchantNotificationSendsMailWhenRecipientIsConfigured(): void
    {
        $this->writeSiteConfiguration(
            'products-with-merchant-notification',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['merchantRecipient' => 'merchant@example.com'],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $order = $this->buildOrder();
        $order->setSiteIdentifier('products-with-merchant-notification');

        $this->subject->sendMerchantNotification($order);

        $sentEmails = TestMailer::getSentEmails();
        self::assertCount(1, $sentEmails);
        self::assertSame('merchant@example.com', $sentEmails[0]->getTo()[0]->getAddress());
        self::assertStringContainsString('ORD-1', (string)$sentEmails[0]->getSubject());
    }

    /**
     * @test
     */
    public function sendOrderConfirmationUsesOverriddenPartialFromFixtureExtension(): void
    {
        $this->writeSiteConfiguration(
            'products-with-partial-override',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => [
                            // Fluid's TemplatePaths resolves partials by walking root paths from the
                            // last entry to the first (later entries act as overlays), so the overriding
                            // path must be listed last.
                            'partialRootPaths' => [
                                'EXT:products/Resources/Private/Partials/',
                                'EXT:frontend_test/Resources/Private/Partials/',
                            ],
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $order = $this->buildOrder();
        $order->setSiteIdentifier('products-with-partial-override');

        $this->subject->sendOrderConfirmation($order);

        $sentEmails = TestMailer::getSentEmails();
        self::assertCount(1, $sentEmails);
        self::assertStringContainsString('FIXTURE-OVERRIDDEN-SIGNATURE', (string)$sentEmails[0]->getHtmlBody());
    }

    private function buildOrder(): Order
    {
        $order = new Order();
        $order->setOrderNumber('ORD-1');
        $order->setEmail('shopper@example.com');
        $order->setCurrency('EUR');
        $order->setTotalGross(Money::fromCents(1999));
        $order->setSiteIdentifier('products');

        return $order;
    }
}
