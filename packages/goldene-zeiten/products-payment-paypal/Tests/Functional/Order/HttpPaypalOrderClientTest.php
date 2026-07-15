<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Tests\Functional\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\Paypal\Exception\PaypalApiException;
use GoldeneZeiten\Products\Payment\Paypal\Tests\Functional\AbstractPaypalMockTestCase;
use PHPUnit\Framework\Attributes\Test;

final class HttpPaypalOrderClientTest extends AbstractPaypalMockTestCase
{
    private const ORDERS_PATH = '/payment/paypal/v2/checkout/orders';

    #[Test]
    public function createsAnOrderAndReturnsTheApprovalUrl(): void
    {
        $order = $this->orderClient()->createOrder($this->order(), $this->context(), $this->configuration());

        $this->assertSame('PAYPAL-ORDER-1', $order->id);
        $this->assertSame('CREATED', $order->status);
        $this->assertStringContainsString('checkoutnow', $order->approveUrl);
    }

    #[Test]
    public function sendsACaptureIntentOrderCarryingTheTotalAndReturnUrls(): void
    {
        $this->orderClient()->createOrder($this->order(), $this->context(), $this->configuration());

        $body = json_decode((string)$this->loggedRequests(self::ORDERS_PATH)[0]['body'], true);
        $this->assertSame('CAPTURE', $body['intent']);
        $this->assertSame('EUR', $body['purchase_units'][0]['amount']['currency_code']);
        $this->assertSame('12.34', $body['purchase_units'][0]['amount']['value']);
        $this->assertSame('https://shop.example/return', $body['payment_source']['paypal']['experience_context']['return_url']);
    }

    #[Test]
    public function capturesAnApprovedOrder(): void
    {
        $capture = $this->orderClient()->capture('PAYPAL-ORDER-1', $this->configuration());

        $this->assertTrue($capture->isCompleted());
        $this->assertSame('CAPTURE-1', $capture->captureId);
    }

    #[Test]
    public function treatsAnAlreadyCapturedOrderAsCompleted(): void
    {
        $capture = $this->orderClient()->capture('PAYPAL-ORDER-CAPTURED', $this->configuration());

        $this->assertTrue($capture->isCompleted());
    }

    #[Test]
    public function retriesOnceWithAFreshTokenAfterUnauthorized(): void
    {
        $capture = $this->orderClient()->capture('PAYPAL-ORDER-RETRY', $this->configuration());

        $this->assertTrue($capture->isCompleted());
        $this->assertSame(2, $this->recordedRequests(self::ORDERS_PATH . '/PAYPAL-ORDER-RETRY/capture'), 'The capture was retried once.');
    }

    #[Test]
    public function raisesOnADeclinedCapture(): void
    {
        $this->expectException(PaypalApiException::class);
        $this->expectExceptionCode(1752600302);
        $this->orderClient()->capture('PAYPAL-ORDER-DECLINE', $this->configuration());
    }

    #[Test]
    public function raisesWhenTheOrderIsRejected(): void
    {
        $this->expectException(PaypalApiException::class);
        $this->expectExceptionCode(1752600301);
        $this->orderClient()->createOrder($this->order(), $this->context('0.00'), $this->configuration());
    }

    private function order(): Order
    {
        $order = new Order();
        $order->setOrderNumber('ORD-1');

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
