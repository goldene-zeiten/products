<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Tests\Functional\Payment;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\Paypal\Configuration\PaypalConfigurationFactory;
use GoldeneZeiten\Products\Payment\Paypal\Payment\PaypalPaymentMethod;
use GoldeneZeiten\Products\Payment\Paypal\Tests\Functional\AbstractPaypalMockTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;

final class PaypalPaymentMethodTest extends AbstractPaypalMockTestCase
{
    private const CAPTURE_RETRY_PATH = '/payment/paypal/v2/checkout/orders/PAYPAL-ORDER-RETRY/capture';

    private const CAPTURE_PATH = '/payment/paypal/v2/checkout/orders/PAYPAL-ORDER-1/capture';

    #[Test]
    public function initiateCreatesAnOrderAndRequiresRedirect(): void
    {
        $result = $this->subject()->initiate($this->order(), $this->context());

        $this->assertSame(PaymentResultState::REDIRECT_REQUIRED, $result->getState());
        $this->assertSame('PAYPAL-ORDER-1', $result->getExternalId());
        $this->assertStringContainsString('checkoutnow', $result->getRedirectUrl());
    }

    #[Test]
    public function initiateFailsWhenPaypalRejectsTheOrder(): void
    {
        $result = $this->subject()->initiate($this->order(), $this->context('0.00'));

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function handleReturnCapturesAndMarksTheOrderPaid(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('PAYPAL-ORDER-1'), $this->order());

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame('CAPTURE-1', $result->getExternalId());
    }

    #[Test]
    public function handleReturnOnAnAlreadyPaidOrderDoesNotCaptureAgain(): void
    {
        $order = $this->order();
        $order->setPaymentStatus(PaymentStatus::PAID);

        $result = $this->subject()->handleReturn($this->returnRequest('PAYPAL-ORDER-1'), $order);

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame(0, $this->recordedRequests(self::CAPTURE_PATH), 'A paid order is not captured again.');
    }

    #[Test]
    public function handleReturnFailsWithoutAToken(): void
    {
        $result = $this->subject()->handleReturn(new ServerRequest('https://shop.example/return', 'GET'), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function handleReturnFailsOnADeclinedCapture(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('PAYPAL-ORDER-DECLINE'), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function handleReturnDeclinedCaptureDoesNotLeakInternalDetailToTheCustomer(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('PAYPAL-ORDER-DECLINE'), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
        $this->assertStringNotContainsString('HTTP 422', $result->getFailureReason(), 'Internal transport detail must not reach the shopper.');
    }

    #[Test]
    public function handleReturnRejectsACaptureBoundToADifferentOrder(): void
    {
        $order = $this->order();
        $order->_setProperty('uid', 999);

        $result = $this->subject()->handleReturn($this->returnRequest('PAYPAL-ORDER-1'), $order);

        $this->assertSame(PaymentResultState::FAILED, $result->getState(), 'A capture for order 42 must not settle order 999.');
    }

    #[Test]
    public function handleReturnRejectsACaptureWithATamperedAmount(): void
    {
        $order = $this->order();
        $order->setTotalGross(Money::fromCents(999999));

        $result = $this->subject()->handleReturn($this->returnRequest('PAYPAL-ORDER-1'), $order);

        $this->assertSame(PaymentResultState::FAILED, $result->getState(), 'A capture paying less than the order total must not settle it.');
    }

    #[Test]
    public function handleReturnRejectsAReplayedForeignAlreadyCapturedOrder(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('PAYPAL-ORDER-CAPTURED'), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState(), 'An already-captured PayPal order belonging elsewhere must not settle this order.');
    }

    #[Test]
    public function handleReturnRetriesAfterUnauthorized(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('PAYPAL-ORDER-RETRY'), $this->order());

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame(2, $this->recordedRequests(self::CAPTURE_RETRY_PATH));
    }

    #[Test]
    public function handleWebhookMarksPaidOnAVerifiedCaptureCompleted(): void
    {
        $result = $this->subject()->handleWebhook($this->webhookRequest('PAYMENT.CAPTURE.COMPLETED', 'CAPTURE-9'), $this->order());

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame('CAPTURE-9', $result->getExternalId());
    }

    #[Test]
    public function handleWebhookRejectsAnUnverifiedSignature(): void
    {
        $result = $this->subject('WEBHOOK-BAD')->handleWebhook($this->webhookRequest('PAYMENT.CAPTURE.COMPLETED', 'CAPTURE-9'), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function handleWebhookFailsOnAnInvalidBody(): void
    {
        $request = new ServerRequest('https://shop.example/webhook', 'POST', $this->stream('not-json'));

        $result = $this->subject()->handleWebhook($request, $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    private function subject(string $webhookId = 'WEBHOOK-OK'): PaypalPaymentMethod
    {
        return new PaypalPaymentMethod(
            $this->configurationFactory($webhookId),
            $this->orderClient(),
            $this->webhookVerifier(),
            $this->get(OrderRepository::class),
            new NullLogger(),
        );
    }

    private function configurationFactory(string $webhookId): PaypalConfigurationFactory
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn([
            'environment' => 'sandbox',
            'clientId' => 'mock-client',
            'clientSecret' => 'secret',
            'webhookId' => $webhookId,
            'brandName' => 'Test Shop',
            'apiBaseUrl' => $this->mockRoot . '/payment/paypal',
        ]);

        return new PaypalConfigurationFactory(new ApiSettingsResolver($extensionConfiguration), new CurrentSiteResolver());
    }

    private function returnRequest(string $paypalOrderId): ServerRequestInterface
    {
        return (new ServerRequest('https://shop.example/return', 'GET'))
            ->withQueryParams(['token' => $paypalOrderId, 'PayerID' => 'PAYER-1']);
    }

    #[Test]
    public function handleStaticWebhookResolvesTheOrderFromTheCaptureAndMarksItPaid(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_for_webhook.csv');

        $resolution = $this->subject()->handleStaticWebhook($this->staticWebhookRequest('42', '12.34', 'EUR'));

        $this->assertInstanceOf(Order::class, $resolution->getOrder());
        $this->assertSame(42, $resolution->getOrder()->getUid());
        $this->assertSame(PaymentStatus::PAID, $resolution->getResult()->getPaymentStatus());
    }

    #[Test]
    public function handleStaticWebhookRejectsACaptureWithATamperedAmount(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_for_webhook.csv');

        $resolution = $this->subject()->handleStaticWebhook($this->staticWebhookRequest('42', '1.00', 'EUR'));

        $this->assertNull($resolution->getOrder(), 'A webhook capture whose amount does not match the order must not resolve it.');
    }

    #[Test]
    public function handleStaticWebhookRejectsAnUnverifiedSignature(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_for_webhook.csv');

        $resolution = $this->subject('WEBHOOK-BAD')->handleStaticWebhook($this->staticWebhookRequest('42', '12.34', 'EUR'));

        $this->assertNull($resolution->getOrder(), 'An unverified webhook must not resolve an order.');
    }

    private function staticWebhookRequest(string $customId, string $amountValue, string $currency): ServerRequestInterface
    {
        $body = json_encode(
            [
                'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
                'resource' => [
                    'id' => 'CAPTURE-9',
                    'custom_id' => $customId,
                    'amount' => [
                        'value' => $amountValue,
                        'currency_code' => $currency,
                    ],
                ],
            ],
            JSON_THROW_ON_ERROR,
        );

        return new ServerRequest('https://shop.example/webhook', 'POST', $this->stream($body), [
            'PayPal-Transmission-Id' => 'txn-1',
            'PayPal-Transmission-Time' => '2026-07-15T12:00:00Z',
            'PayPal-Transmission-Sig' => 'signature',
            'PayPal-Cert-Url' => 'https://api.paypal.com/cert.pem',
            'PayPal-Auth-Algo' => 'SHA256withRSA',
        ]);
    }

    private function webhookRequest(string $eventType, string $resourceId): ServerRequestInterface
    {
        $body = json_encode(
            [
                'event_type' => $eventType,
                'resource' => [
                    'id' => $resourceId,
                ],
            ],
            JSON_THROW_ON_ERROR,
        );

        return new ServerRequest('https://shop.example/webhook', 'POST', $this->stream($body), [
            'PayPal-Transmission-Id' => 'txn-1',
            'PayPal-Transmission-Time' => '2026-07-15T12:00:00Z',
            'PayPal-Transmission-Sig' => 'signature',
            'PayPal-Cert-Url' => 'https://api.paypal.com/cert.pem',
            'PayPal-Auth-Algo' => 'SHA256withRSA',
        ]);
    }

    private function stream(string $body): Stream
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write($body);
        $stream->rewind();

        return $stream;
    }

    private function order(): Order
    {
        $order = new Order();
        $order->_setProperty('uid', 42);
        $order->setOrderNumber('ORD-1');
        $order->setCurrency('EUR');
        $order->setTotalGross(Money::fromCents(1234));

        return $order;
    }

    private function context(string $amount = '12.34'): PaymentContext
    {
        return new PaymentContext(
            Money::fromDecimalString($amount),
            'EUR',
            'DE',
            0,
            'https://shop.example/return',
            'https://shop.example/cancel',
            'https://shop.example/webhook',
        );
    }
}
