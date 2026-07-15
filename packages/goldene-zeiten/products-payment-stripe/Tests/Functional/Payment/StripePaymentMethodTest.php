<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Stripe\Tests\Functional\Payment;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\Stripe\Client\StripeClientFactory;
use GoldeneZeiten\Products\Payment\Stripe\Configuration\StripeConfigurationFactory;
use GoldeneZeiten\Products\Payment\Stripe\Payment\StripePaymentMethod;
use GoldeneZeiten\Products\Payment\Stripe\Tests\Functional\AbstractStripeMockTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;

final class StripePaymentMethodTest extends AbstractStripeMockTestCase
{
    private const RETRIEVE_PATH = '/payment/stripe/v1/checkout/sessions/cs_test_1';

    #[Test]
    public function initiateCreatesASessionAndRequiresRedirect(): void
    {
        $result = $this->subject()->initiate($this->order(), $this->context());

        $this->assertSame(PaymentResultState::REDIRECT_REQUIRED, $result->getState());
        $this->assertSame('cs_test_1', $result->getExternalId());
        $this->assertStringContainsString('checkout.stripe.com', $result->getRedirectUrl());
    }

    #[Test]
    public function initiateFailsWithoutAConfiguredCheckoutPage(): void
    {
        $result = $this->subject()->initiate($this->order(), $this->context('', ''));

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function handleReturnConfirmsAPaidSession(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('cs_test_1'), $this->order());

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame('pi_test_1', $result->getExternalId());
    }

    #[Test]
    public function handleReturnOnAnAlreadyPaidOrderDoesNotCallStripe(): void
    {
        $order = $this->order();
        $order->setPaymentStatus(PaymentStatus::PAID);

        $result = $this->subject()->handleReturn($this->returnRequest('cs_test_1'), $order);

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame(0, $this->recordedRequests(self::RETRIEVE_PATH, 'GET'), 'A paid order is not re-confirmed.');
    }

    #[Test]
    public function handleReturnFailsForAnUnpaidSession(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('cs_test_unpaid'), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function handleReturnFailsWithoutASessionId(): void
    {
        $result = $this->subject()->handleReturn(new ServerRequest('https://shop.example/return', 'GET'), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function handleWebhookMarksPaidOnAVerifiedCompletedSession(): void
    {
        $result = $this->subject()->handleWebhook($this->webhookRequest(true), $this->order());

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame('pi_test_1', $result->getExternalId());
    }

    #[Test]
    public function handleWebhookRejectsAnInvalidSignature(): void
    {
        $result = $this->subject()->handleWebhook($this->webhookRequest(false), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    private function subject(): StripePaymentMethod
    {
        return new StripePaymentMethod(
            $this->configurationFactory(),
            new StripeClientFactory(),
            $this->get(\Psr\EventDispatcher\EventDispatcherInterface::class),
            new NullLogger(),
        );
    }

    private function configurationFactory(): StripeConfigurationFactory
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn([
            'secretKey' => 'sk_test_x',
            'webhookSecret' => 'whsec_test',
            'apiBaseUrl' => $this->mockRoot . '/payment/stripe',
        ]);

        return new StripeConfigurationFactory(new ApiSettingsResolver($extensionConfiguration), new CurrentSiteResolver());
    }

    private function returnRequest(string $sessionId): ServerRequestInterface
    {
        return (new ServerRequest('https://shop.example/return', 'GET'))->withQueryParams(['session_id' => $sessionId]);
    }

    private function webhookRequest(bool $validSignature): ServerRequestInterface
    {
        $payload = json_encode(
            [
                'id' => 'evt_1',
                'object' => 'event',
                'type' => 'checkout.session.completed',
                'data' => [
                    'object' => [
                        'id' => 'cs_test_1',
                        'object' => 'checkout.session',
                        'payment_status' => 'paid',
                        'payment_intent' => 'pi_test_1',
                    ],
                ],
            ],
            JSON_THROW_ON_ERROR,
        );
        $timestamp = time();
        $signature = $validSignature
            ? 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, 'whsec_test')
            : 't=' . $timestamp . ',v1=deadbeef';

        return new ServerRequest('https://shop.example/webhook', 'POST', $this->stream($payload), [
            'Stripe-Signature' => $signature,
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
        $order->setOrderNumber('ORD-1');

        return $order;
    }

    private function context(string $returnUrl = 'https://shop.example/return', string $cancelUrl = 'https://shop.example/cancel'): PaymentContext
    {
        return new PaymentContext(Money::fromDecimalString('12.34'), 'EUR', 'DE', 0, $returnUrl, $cancelUrl, 'https://shop.example/webhook');
    }
}
