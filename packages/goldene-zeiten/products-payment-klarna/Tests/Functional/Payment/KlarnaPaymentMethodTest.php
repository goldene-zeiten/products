<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Tests\Functional\Payment;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\Klarna\Configuration\KlarnaConfigurationFactory;
use GoldeneZeiten\Products\Payment\Klarna\Order\KlarnaOrderPayloadBuilder;
use GoldeneZeiten\Products\Payment\Klarna\Payment\KlarnaPaymentMethod;
use GoldeneZeiten\Products\Payment\Klarna\Tests\Functional\AbstractKlarnaMockTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;

final class KlarnaPaymentMethodTest extends AbstractKlarnaMockTestCase
{
    private const PLACE_ORDER_PATH = '/payment/klarna/payments/v1/authorizations/auth_token_1/order';

    #[Test]
    public function initiateOpensASessionAndRequiresRedirect(): void
    {
        $result = $this->subject()->initiate($this->order(), $this->context());

        $this->assertSame(PaymentResultState::REDIRECT_REQUIRED, $result->getState());
        $this->assertSame('hpp_session_1', $result->getExternalId());
        $this->assertStringContainsString('hpp', $result->getRedirectUrl());
    }

    #[Test]
    public function initiateFailsWithoutAConfiguredCheckoutPage(): void
    {
        $result = $this->subject()->initiate($this->order(), $this->context('', ''));

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function handleReturnPlacesTheOrderAndMarksPaid(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('auth_token_1'), $this->order());

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame('klarna_order_1', $result->getExternalId());
    }

    #[Test]
    public function handleReturnFailsOnARefusedOrder(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('auth_token_reject'), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function handleReturnOnAnAlreadyPaidOrderDoesNotPlaceAgain(): void
    {
        $order = $this->order();
        $order->setPaymentStatus(PaymentStatus::PAID);

        $result = $this->subject()->handleReturn($this->returnRequest('auth_token_1'), $order);

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame(0, $this->recordedRequests(self::PLACE_ORDER_PATH), 'A paid order is not placed again.');
    }

    #[Test]
    public function handleReturnFailsWithoutAnAuthorizationToken(): void
    {
        $result = $this->subject()->handleReturn(new ServerRequest('https://shop.example/return', 'GET'), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function handleWebhookVerifiesTheSessionAndMarksPaid(): void
    {
        $result = $this->subject()->handleWebhook($this->webhookRequest('hpp_session_1'), $this->order());

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
    }

    #[Test]
    public function handleWebhookStaysPendingForAWaitingSession(): void
    {
        $result = $this->subject()->handleWebhook($this->webhookRequest('hpp_session_waiting'), $this->order());

        $this->assertSame(PaymentResultState::PENDING, $result->getState());
    }

    #[Test]
    public function handleWebhookFailsWithoutASessionId(): void
    {
        $request = new ServerRequest('https://shop.example/webhook', 'POST', $this->stream('{}'));

        $result = $this->subject()->handleWebhook($request, $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    private function subject(): KlarnaPaymentMethod
    {
        return new KlarnaPaymentMethod(
            $this->configurationFactory(),
            $this->client(),
            new KlarnaOrderPayloadBuilder(),
            $this->get(EventDispatcherInterface::class),
            new NullLogger(),
        );
    }

    private function configurationFactory(): KlarnaConfigurationFactory
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn([
            'environment' => 'playground',
            'username' => 'mock-user',
            'password' => 'mock-pass',
            'apiBaseUrl' => $this->mockRoot . '/payment/klarna',
        ]);

        return new KlarnaConfigurationFactory(new ApiSettingsResolver($extensionConfiguration), new CurrentSiteResolver());
    }

    private function returnRequest(string $authorizationToken): ServerRequestInterface
    {
        return (new ServerRequest('https://shop.example/return', 'GET'))
            ->withQueryParams(['authorization_token' => $authorizationToken]);
    }

    private function webhookRequest(string $sessionId): ServerRequestInterface
    {
        $body = json_encode(
            [
                'event_id' => 'evt-1',
                'session' => [
                    'session_id' => $sessionId,
                ],
            ],
            JSON_THROW_ON_ERROR,
        );

        return new ServerRequest('https://shop.example/webhook', 'POST', $this->stream($body));
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
        $order->setCurrency('EUR');
        $order->setTotalGross(Money::fromCents(1234));

        return $order;
    }

    private function context(string $returnUrl = 'https://shop.example/return', string $cancelUrl = 'https://shop.example/cancel'): PaymentContext
    {
        return new PaymentContext(Money::fromDecimalString('12.34'), 'EUR', 'DE', 0, $returnUrl, $cancelUrl, 'https://shop.example/webhook');
    }
}
