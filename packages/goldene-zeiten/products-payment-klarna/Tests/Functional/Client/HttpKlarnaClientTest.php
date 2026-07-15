<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Tests\Functional\Client;

use GoldeneZeiten\Products\Payment\Klarna\Exception\KlarnaApiException;
use GoldeneZeiten\Products\Payment\Klarna\Tests\Functional\AbstractKlarnaMockTestCase;
use PHPUnit\Framework\Attributes\Test;

final class HttpKlarnaClientTest extends AbstractKlarnaMockTestCase
{
    #[Test]
    public function opensAPaymentSession(): void
    {
        $sessionId = $this->client()->createPaymentSession(['order_amount' => 1234], $this->configuration());

        $this->assertSame('kp_session_1', $sessionId);
    }

    #[Test]
    public function createsAnHppSessionWithARedirectUrl(): void
    {
        $hppSession = $this->client()->createHppSession('kp_session_1', $this->merchantUrls(), $this->configuration());

        $this->assertSame('hpp_session_1', $hppSession->hppSessionId);
        $this->assertStringContainsString('hpp', $hppSession->redirectUrl);
    }

    #[Test]
    public function readsACompletedSession(): void
    {
        $status = $this->client()->readHppSession('hpp_session_1', $this->configuration());

        $this->assertTrue($status->isCompleted());
        $this->assertSame('auth_token_1', $status->authorizationToken);
    }

    #[Test]
    public function readsAWaitingSession(): void
    {
        $status = $this->client()->readHppSession('hpp_session_waiting', $this->configuration());

        $this->assertFalse($status->isCompleted());
    }

    #[Test]
    public function placesAnAcceptedOrder(): void
    {
        $order = $this->client()->placeOrder('auth_token_1', ['order_amount' => 1234], $this->configuration());

        $this->assertTrue($order->isAccepted());
        $this->assertSame('klarna_order_1', $order->orderId);
    }

    #[Test]
    public function returnsARejectedOrder(): void
    {
        $order = $this->client()->placeOrder('auth_token_reject', ['order_amount' => 1234], $this->configuration());

        $this->assertSame('REJECTED', $order->fraudStatus);
    }

    #[Test]
    public function raisesOnAnUnknownAuthorizationToken(): void
    {
        $this->expectException(KlarnaApiException::class);
        $this->expectExceptionCode(1752600603);
        $this->client()->placeOrder('auth_token_bad', ['order_amount' => 1234], $this->configuration());
    }

    /**
     * @return array<string, string>
     */
    private function merchantUrls(): array
    {
        return [
            'success' => 'https://shop.example/return',
            'cancel' => 'https://shop.example/cancel',
            'back' => 'https://shop.example/cancel',
            'failure' => 'https://shop.example/cancel',
            'error' => 'https://shop.example/cancel',
            'status_update' => 'https://shop.example/webhook',
        ];
    }
}
