<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Tests\Functional\Payment;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\Amazon\Configuration\AmazonPayConfigurationFactory;
use GoldeneZeiten\Products\Payment\Amazon\Payment\AmazonPayPaymentMethod;
use GoldeneZeiten\Products\Payment\Amazon\Tests\Functional\AbstractAmazonPayMockTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\ServerRequest;

final class AmazonPayPaymentMethodTest extends AbstractAmazonPayMockTestCase
{
    private const COMPLETE_PATH = '/payment/amazon/v2/checkoutSessions/amzn_session_1/complete';

    #[Test]
    public function initiateOpensASessionAndRedirectsToAmazon(): void
    {
        $result = $this->subject()->initiate($this->order(), $this->context());

        $this->assertSame(PaymentResultState::REDIRECT_REQUIRED, $result->getState());
        $this->assertSame('amzn_session_1', $result->getExternalId());
        $this->assertStringContainsString('pay.amazon.eu', $result->getRedirectUrl());
        $this->assertStringContainsString('amzn_session_1', $result->getRedirectUrl());
    }

    #[Test]
    public function initiateFailsWithoutAConfiguredCheckoutPage(): void
    {
        $result = $this->subject()->initiate($this->order(), $this->context('', ''));

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function theReviewLegUpdatesTheSessionAndRedirectsBackToAmazon(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('review'), $this->order());

        $this->assertSame(PaymentResultState::REDIRECT_REQUIRED, $result->getState());
        $this->assertSame('https://pay.amazon.eu/checkout/amzn_session_1', $result->getRedirectUrl());
    }

    #[Test]
    public function theResultLegCompletesTheChargeAndMarksPaid(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('result'), $this->order());

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame('S01-0000000-0000000-C000000', $result->getExternalId());
    }

    #[Test]
    public function theResultLegOnAnAlreadyPaidOrderDoesNotCompleteAgain(): void
    {
        $order = $this->order();
        $order->setPaymentStatus(PaymentStatus::PAID);

        $result = $this->subject()->handleReturn($this->returnRequest('result'), $order);

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame(0, $this->recordedRequests(self::COMPLETE_PATH), 'A paid order is not completed again.');
    }

    #[Test]
    public function handleReturnFailsWithoutACheckoutSessionId(): void
    {
        $result = $this->subject()->handleReturn(new ServerRequest('https://shop.example/return', 'GET'), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function handleWebhookLeavesThePaymentUnchangedWithoutASessionId(): void
    {
        $result = $this->subject()->handleWebhook(new ServerRequest('https://shop.example/webhook', 'POST'), $this->order());

        $this->assertSame(PaymentResultState::UNCHANGED, $result->getState());
    }

    #[Test]
    public function handleWebhookMarksPaidForACompletedSessionOfThisOrder(): void
    {
        $result = $this->subject()->handleWebhook($this->webhookRequest('amzn_session_paid'), $this->order());

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
    }

    /**
     * The session id comes from the caller. A completed session of a different order - or of this order at
     * a different amount - proves nothing about this order and must not mark it paid.
     */
    #[Test]
    #[DataProvider('foreignSessions')]
    public function handleWebhookDoesNotMarkPaidForACompletedSessionThatIsNotThisOrders(string $sessionId): void
    {
        $result = $this->subject()->handleWebhook($this->webhookRequest($sessionId), $this->order());

        $this->assertNotSame(PaymentStatus::PAID, $result->getPaymentStatus());
    }

    #[Test]
    #[DataProvider('foreignSessions')]
    public function theResultLegDoesNotSettleTheOrderWithASessionThatIsNotItsOwn(string $sessionId): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('result', $sessionId), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
        $this->assertSame(0, $this->recordedRequests('/payment/amazon/v2/checkoutSessions/' . $sessionId . '/complete'));
    }

    public static function foreignSessions(): \Generator
    {
        yield 'a session of another order' => [
            'sessionId' => 'amzn_session_foreign',
        ];
        yield 'a session of this order at another amount' => [
            'sessionId' => 'amzn_session_cheap',
        ];
    }

    private function subject(): AmazonPayPaymentMethod
    {
        return new AmazonPayPaymentMethod(
            $this->configurationFactory(),
            $this->client(),
            $this->get(EventDispatcherInterface::class),
            new NullLogger(),
        );
    }

    private function configurationFactory(): AmazonPayConfigurationFactory
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn([
            'region' => 'eu',
            'environment' => 'sandbox',
            'publicKeyId' => 'SANDBOX-AMZN-TEST-KEY',
            'privateKey' => (string)file_get_contents(__DIR__ . '/../Fixtures/test_private_key.pem'),
            'storeId' => 'amzn1.application-oa2-client.test',
            'merchantStoreName' => 'Test Shop',
            'apiBaseUrl' => $this->mockRoot . '/payment/amazon',
        ]);

        return new AmazonPayConfigurationFactory(new ApiSettingsResolver($extensionConfiguration), new CurrentSiteResolver());
    }

    private function returnRequest(string $leg, string $sessionId = 'amzn_session_1'): ServerRequestInterface
    {
        return (new ServerRequest('https://shop.example/products/payment/return?order=1&signature=sig&leg=' . $leg . '&amazonCheckoutSessionId=' . $sessionId, 'GET'))
            ->withQueryParams(['order' => '1', 'signature' => 'sig', 'leg' => $leg, 'amazonCheckoutSessionId' => $sessionId]);
    }

    private function webhookRequest(string $sessionId): ServerRequestInterface
    {
        return (new ServerRequest('https://shop.example/products/payment/webhook?order=1&signature=sig&amazonCheckoutSessionId=' . $sessionId, 'POST'))
            ->withQueryParams(['order' => '1', 'signature' => 'sig', 'amazonCheckoutSessionId' => $sessionId]);
    }

    private function order(): Order
    {
        $order = new Order();
        $order->setOrderNumber('ORD-1');
        $order->setCurrency('EUR');
        $order->setTotalGross(Money::fromCents(5490));

        return $order;
    }

    private function context(string $returnUrl = 'https://shop.example/products/payment/return?order=1&signature=sig', string $cancelUrl = 'https://shop.example/products/payment/cancel'): PaymentContext
    {
        return new PaymentContext(Money::fromCents(5490), 'EUR', 'DE', 0, $returnUrl, $cancelUrl, 'https://shop.example/products/payment/webhook');
    }
}
