<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Payment;

use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Payment\Exception\PaymentCallbackException;
use GoldeneZeiten\Products\Core\Payment\PaymentCallbackService;
use GoldeneZeiten\Products\Core\Payment\PaymentTokenService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

final class PaymentCallbackServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-payment-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/orders_for_callback_test.csv');
    }

    #[Test]
    public function handleReturnWithValidTokenReturnsPaidResultAndFinalizesTheOrder(): void
    {
        $order = $this->fetchOrder(1);
        $tokenService = $this->get(PaymentTokenService::class);
        $token = $tokenService->generateToken($order);
        $subject = $this->get(PaymentCallbackService::class);

        $result = $subject->handleReturn(1, $token, $this->request());

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame(PaymentStatus::PAID, $this->fetchOrder(1)->getPaymentStatus());
    }

    #[Test]
    public function handleReturnOfAMultiHopGatewayReturnsTheRedirectWithoutFinalizing(): void
    {
        $order = $this->fetchOrder(2);
        $token = $this->get(PaymentTokenService::class)->generateToken($order);
        $subject = $this->get(PaymentCallbackService::class);

        $result = $subject->handleReturn(2, $token, $this->request());

        $this->assertSame(PaymentResultState::REDIRECT_REQUIRED, $result->getState());
        $this->assertSame('https://gateway.example/second-hop', $result->getRedirectUrl());
        // The order must stay open: the customer still has to complete the second hop at the gateway.
        $this->assertSame(PaymentStatus::OPEN, $this->fetchOrder(2)->getPaymentStatus());
    }

    #[Test]
    public function handleWebhookWithValidTokenReturnsPaymentResultWithPaid(): void
    {
        $order = $this->fetchOrder(1);
        $tokenService = $this->get(PaymentTokenService::class);
        $token = $tokenService->generateToken($order);
        $subject = $this->get(PaymentCallbackService::class);

        $result = $subject->handleWebhook(1, $token, $this->request());

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
    }

    #[Test]
    public function handleReturnWithInvalidTokenThrowsPaymentCallbackException(): void
    {
        $subject = $this->get(PaymentCallbackService::class);

        $this->expectException(PaymentCallbackException::class);
        $this->expectExceptionCode(1784073610);

        $subject->handleReturn(1, 'invalid-token', $this->request());
    }

    #[Test]
    public function handleReturnWithEmptyTokenThrowsPaymentCallbackException(): void
    {
        $subject = $this->get(PaymentCallbackService::class);

        $this->expectException(PaymentCallbackException::class);
        $this->expectExceptionCode(1784073610);

        $subject->handleReturn(1, '', $this->request());
    }

    #[Test]
    public function resolveOrderWithUnknownUidThrowsPaymentCallbackException(): void
    {
        $subject = $this->get(PaymentCallbackService::class);

        $this->expectException(PaymentCallbackException::class);
        $this->expectExceptionCode(1784073610);

        $subject->resolveOrder(99999, 'any-token');
    }

    #[Test]
    public function replayingHandleReturnTwiceIsIdempotent(): void
    {
        $order = $this->fetchOrder(1);
        $tokenService = $this->get(PaymentTokenService::class);
        $token = $tokenService->generateToken($order);
        $subject = $this->get(PaymentCallbackService::class);

        // First call
        $subject->handleReturn(1, $token, $this->request());
        $refreshedOrderAfterFirst = $this->fetchOrder(1);
        $this->assertSame(PaymentStatus::PAID, $refreshedOrderAfterFirst->getPaymentStatus());

        // Second call (replay)
        $subject->handleReturn(1, $token, $this->request());
        $refreshedOrderAfterSecond = $this->fetchOrder(1);
        $this->assertSame(PaymentStatus::PAID, $refreshedOrderAfterSecond->getPaymentStatus());
    }

    private function fetchOrder(int $uid): Order
    {
        $order = $this->get(OrderRepository::class)->findByUid($uid);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }

    private function request(): ServerRequestInterface
    {
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }
}
