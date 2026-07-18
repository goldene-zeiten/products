<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Payment;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Payment\PaymentTokenService;
use GoldeneZeiten\Products\Core\Payment\PaymentUrlFactory;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * A redirect payment method is useless unless it can tell its gateway where to send the customer back to
 * and where to post its confirmation. This proves those URLs are actually filled in - and signed.
 */
final class PaymentUrlFactoryTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_url_test.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/orders_for_callback_test.csv');
    }

    #[Test]
    public function theReturnUrlIsTheFixedReturnPathCarryingTheSignedOrder(): void
    {
        $subject = $this->get(PaymentUrlFactory::class);
        $order = $this->order();

        $returnUrl = $subject->createFor($order, $this->requestWithCheckoutPage(5))->getReturnUrl();

        $this->assertStringStartsWith('http', $returnUrl);
        $this->assertStringContainsString(PaymentUrlFactory::RETURN_PATH, $returnUrl);
        $this->assertStringContainsString('order=1', $returnUrl);
        $this->assertStringContainsString('signature=' . $this->token($order), $returnUrl);
    }

    #[Test]
    public function theCancelUrlIsTheFixedCancelPath(): void
    {
        $subject = $this->get(PaymentUrlFactory::class);

        $cancelUrl = $subject->createFor($this->order(), $this->requestWithCheckoutPage(5))->getCancelUrl();

        $this->assertStringContainsString(PaymentUrlFactory::CANCEL_PATH, $cancelUrl);
        $this->assertStringContainsString('order=1', $cancelUrl);
    }

    #[Test]
    public function theWebhookUrlIsAbsoluteAndCarriesTheSignedOrder(): void
    {
        $subject = $this->get(PaymentUrlFactory::class);
        $order = $this->order();

        $webhookUrl = $subject->createFor($order, $this->requestWithCheckoutPage(5))->getWebhookUrl();

        $this->assertStringStartsWith('http', $webhookUrl);
        $this->assertStringContainsString(PaymentUrlFactory::WEBHOOK_PATH, $webhookUrl);
        $this->assertStringContainsString('order=1', $webhookUrl);
        $this->assertStringContainsString('signature=' . $this->token($order), $webhookUrl);
    }

    /**
     * The webhook is called by the gateway, not a browser, so it does not depend on a checkout page the
     * way the customer-facing callbacks do.
     */
    #[Test]
    public function withoutAConfiguredCheckoutPageOnlyTheWebhookSurvives(): void
    {
        $subject = $this->get(PaymentUrlFactory::class);

        $urls = $subject->createFor($this->order(), $this->requestWithCheckoutPage(0));

        $this->assertSame('', $urls->getReturnUrl());
        $this->assertSame('', $urls->getCancelUrl());
        $this->assertStringContainsString(PaymentUrlFactory::WEBHOOK_PATH, $urls->getWebhookUrl());
    }

    #[Test]
    public function withoutASiteNoCallbackUrlCanBeBuilt(): void
    {
        $subject = $this->get(PaymentUrlFactory::class);

        $urls = $subject->createFor($this->order(), new ServerRequest('http://localhost/'));

        $this->assertSame('', $urls->getReturnUrl());
        $this->assertSame('', $urls->getCancelUrl());
        $this->assertSame('', $urls->getWebhookUrl());
    }

    private function token(Order $order): string
    {
        return $this->get(PaymentTokenService::class)->generateToken($order);
    }

    private function order(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUid(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }

    private function requestWithCheckoutPage(int $checkoutPage): ServerRequestInterface
    {
        $site = new Site('products', 1, [
            'base' => 'http://localhost/',
            'rootPageId' => 1,
            'settings' => ['products' => ['pids' => ['checkoutPage' => $checkoutPage]]],
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'locale' => 'en_US.UTF-8',
                    'base' => '/',
                ],
            ],
        ]);

        return (new ServerRequest('http://localhost/'))->withAttribute('site', $site);
    }
}
