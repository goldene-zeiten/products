<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Payment;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Payment\PaymentTokenService;
use GoldeneZeiten\Products\Payment\PaymentUrlFactory;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * A redirect payment method is useless unless it can tell its gateway where to send the customer back to
 * and where to post its confirmation. These URLs used to be empty strings, which made every redirect
 * gateway unimplementable, so what matters here is that they are actually filled in - and signed.
 */
final class PaymentUrlFactoryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_url_test.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/orders_for_callback_test.csv');
    }

    #[Test]
    public function theReturnUrlPointsAtTheCheckoutPageAndCarriesTheSignedOrder(): void
    {
        $subject = $this->get(PaymentUrlFactory::class);
        $order = $this->order();

        $returnUrl = $subject->createFor($order, $this->requestWithCheckoutPage(5))->getReturnUrl();

        $this->assertStringContainsString('/checkout', $returnUrl);
        $this->assertStringContainsString('paymentReturn', $returnUrl);
        $this->assertStringContainsString('[order]=1', urldecode($returnUrl));
        $this->assertStringContainsString($this->token($order), urldecode($returnUrl));
    }

    #[Test]
    public function theCancelUrlPointsAtTheCancelAction(): void
    {
        $subject = $this->get(PaymentUrlFactory::class);

        $cancelUrl = $subject->createFor($this->order(), $this->requestWithCheckoutPage(5))->getCancelUrl();

        $this->assertStringContainsString('paymentCancel', $cancelUrl);
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
        $this->assertStringContainsString('token=' . $this->token($order), $webhookUrl);
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
