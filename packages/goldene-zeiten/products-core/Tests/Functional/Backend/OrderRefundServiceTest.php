<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Backend;

use GoldeneZeiten\Products\Core\Backend\Exception\OrderAccessDeniedException;
use GoldeneZeiten\Products\Core\Backend\Exception\OrderNotRefundableException;
use GoldeneZeiten\Products\Core\Backend\Exception\OrderRefundFailedException;
use GoldeneZeiten\Products\Core\Backend\OrderAccessGuard;
use GoldeneZeiten\Products\Core\Backend\OrderDataFactory;
use GoldeneZeiten\Products\Core\Backend\OrderRefundClaim;
use GoldeneZeiten\Products\Core\Backend\OrderRefundService;
use GoldeneZeiten\Products\Core\Backend\OrderStatusWriter;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\RecordingRefundPaymentMethod;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * The payment registry is the one dependency replaced: it holds a refundable double that counts the
 * gateway calls, which is the money actually leaving the shop.
 */
final class OrderRefundServiceTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderRefundServiceTest/orders.csv');
    }

    #[Test]
    public function aPaidOrderIsRefundedOnce(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $gateway = new RecordingRefundPaymentMethod();
        $subject = new OrderRefundService(
            $this->get(ConnectionPool::class),
            $this->get(OrderAccessGuard::class),
            new PaymentMethodRegistry([$gateway], $this->get(EventDispatcherInterface::class)),
            $this->get(OrderDataFactory::class),
            $this->get(OrderStatusWriter::class),
            $this->get(OrderRefundClaim::class),
        );

        $subject->refund(1, $backendUser);

        $this->assertSame(1, $gateway->refundCalls);
        $this->assertSame('refunded', $this->fetchPaymentStatus(1));
    }

    #[Test]
    public function aReplayedRefundNeverReachesTheGatewayAgain(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $gateway = new RecordingRefundPaymentMethod();
        $subject = new OrderRefundService(
            $this->get(ConnectionPool::class),
            $this->get(OrderAccessGuard::class),
            new PaymentMethodRegistry([$gateway], $this->get(EventDispatcherInterface::class)),
            $this->get(OrderDataFactory::class),
            $this->get(OrderStatusWriter::class),
            $this->get(OrderRefundClaim::class),
        );
        $subject->refund(1, $backendUser);

        try {
            $subject->refund(1, $backendUser);
            $this->fail('A replayed refund must be rejected.');
        } catch (OrderNotRefundableException $exception) {
            $this->assertSame(1791663248, $exception->getCode());
        }

        $this->assertSame(1, $gateway->refundCalls);
    }

    #[Test]
    public function anUnpaidOrderIsRejectedBeforeTheGatewayIsCalled(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $gateway = new RecordingRefundPaymentMethod();
        $subject = new OrderRefundService(
            $this->get(ConnectionPool::class),
            $this->get(OrderAccessGuard::class),
            new PaymentMethodRegistry([$gateway], $this->get(EventDispatcherInterface::class)),
            $this->get(OrderDataFactory::class),
            $this->get(OrderStatusWriter::class),
            $this->get(OrderRefundClaim::class),
        );

        try {
            $subject->refund(2, $backendUser);
            $this->fail('An open order must not be refunded.');
        } catch (OrderNotRefundableException $exception) {
            $this->assertSame(1791663248, $exception->getCode());
        }

        $this->assertSame(0, $gateway->refundCalls);
        $this->assertSame('open', $this->fetchPaymentStatus(2));
    }

    #[Test]
    public function aUserWhoMayOnlyReadTheOrderIsRejectedBeforeTheGatewayIsCalled(): void
    {
        $backendUser = $this->setUpBackendUser(2);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $gateway = new RecordingRefundPaymentMethod();
        $subject = new OrderRefundService(
            $this->get(ConnectionPool::class),
            $this->get(OrderAccessGuard::class),
            new PaymentMethodRegistry([$gateway], $this->get(EventDispatcherInterface::class)),
            $this->get(OrderDataFactory::class),
            $this->get(OrderStatusWriter::class),
            $this->get(OrderRefundClaim::class),
        );

        try {
            $subject->refund(1, $backendUser);
            $this->fail('A read-only user must not refund.');
        } catch (OrderAccessDeniedException $exception) {
            $this->assertSame(1791663246, $exception->getCode());
        }

        $this->assertSame(0, $gateway->refundCalls);
        $this->assertSame('paid', $this->fetchPaymentStatus(1));
    }

    #[Test]
    public function aUserWithWriteAccessToTheOrderMayRefund(): void
    {
        $backendUser = $this->setUpBackendUser(3);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $gateway = new RecordingRefundPaymentMethod();
        $subject = new OrderRefundService(
            $this->get(ConnectionPool::class),
            $this->get(OrderAccessGuard::class),
            new PaymentMethodRegistry([$gateway], $this->get(EventDispatcherInterface::class)),
            $this->get(OrderDataFactory::class),
            $this->get(OrderStatusWriter::class),
            $this->get(OrderRefundClaim::class),
        );

        $subject->refund(1, $backendUser);

        $this->assertSame(1, $gateway->refundCalls);
        $this->assertSame('refunded', $this->fetchPaymentStatus(1));
    }

    /**
     * A second request arriving while the first one is still talking to the gateway must not refund again.
     */
    #[Test]
    public function aSimultaneousSecondRefundIsRejectedWhileTheFirstIsInFlight(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $rejection = null;
        $statusDuringRefund = null;
        $subject = null;
        $gateway = new RecordingRefundPaymentMethod(duringRefund: function () use (&$subject, &$rejection, &$statusDuringRefund, $backendUser): void {
            $statusDuringRefund = $this->fetchPaymentStatus(1);
            self::assertInstanceOf(OrderRefundService::class, $subject);
            try {
                $subject->refund(1, $backendUser);
            } catch (OrderNotRefundableException $exception) {
                $rejection = $exception;
            }
        });
        $subject = new OrderRefundService(
            $this->get(ConnectionPool::class),
            $this->get(OrderAccessGuard::class),
            new PaymentMethodRegistry([$gateway], $this->get(EventDispatcherInterface::class)),
            $this->get(OrderDataFactory::class),
            $this->get(OrderStatusWriter::class),
            $this->get(OrderRefundClaim::class),
        );

        $subject->refund(1, $backendUser);

        $this->assertSame('refund_pending', $statusDuringRefund);
        $this->assertInstanceOf(OrderNotRefundableException::class, $rejection);
        $this->assertSame(1, $gateway->refundCalls);
        $this->assertSame('refunded', $this->fetchPaymentStatus(1));
    }

    #[Test]
    public function aRefundTheGatewayOnlyAcceptsStaysPendingAndCannotBeRefundedAgain(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $gateway = new RecordingRefundPaymentMethod(PaymentResult::pending('REFUND-1'));
        $subject = new OrderRefundService(
            $this->get(ConnectionPool::class),
            $this->get(OrderAccessGuard::class),
            new PaymentMethodRegistry([$gateway], $this->get(EventDispatcherInterface::class)),
            $this->get(OrderDataFactory::class),
            $this->get(OrderStatusWriter::class),
            $this->get(OrderRefundClaim::class),
        );

        $subject->refund(1, $backendUser);

        $this->assertSame('refund_pending', $this->fetchPaymentStatus(1));
        try {
            $subject->refund(1, $backendUser);
            $this->fail('A refund in progress must not be refunded again.');
        } catch (OrderNotRefundableException $exception) {
            $this->assertSame(1791663248, $exception->getCode());
        }
        $this->assertSame(1, $gateway->refundCalls);
    }

    #[Test]
    public function aPendingRefundIsCompletedByTheBackendAction(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $this->get(ConnectionPool::class)->getConnectionForTable('tx_products_domain_model_order')
            ->update('tx_products_domain_model_order', ['payment_status' => 'refund_pending'], ['uid' => 1]);

        $this->get(OrderStatusWriter::class)->changePaymentStatus(1, PaymentStatus::REFUNDED, $backendUser);

        $this->assertSame('refunded', $this->fetchPaymentStatus(1));
    }

    #[Test]
    public function aGatewayThatFailsTheRefundReleasesTheClaim(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $gateway = new RecordingRefundPaymentMethod(failure: new \RuntimeException('Gateway timeout', 1791700001));
        $subject = new OrderRefundService(
            $this->get(ConnectionPool::class),
            $this->get(OrderAccessGuard::class),
            new PaymentMethodRegistry([$gateway], $this->get(EventDispatcherInterface::class)),
            $this->get(OrderDataFactory::class),
            $this->get(OrderStatusWriter::class),
            $this->get(OrderRefundClaim::class),
        );

        try {
            $subject->refund(1, $backendUser);
            $this->fail('The gateway failure must reach the caller.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(1791700001, $exception->getCode());
        }

        $this->assertSame('paid', $this->fetchPaymentStatus(1));
    }

    #[Test]
    public function aGatewayThatDeclinesTheRefundReleasesTheClaim(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $gateway = new RecordingRefundPaymentMethod(PaymentResult::failed('Insufficient balance'));
        $subject = new OrderRefundService(
            $this->get(ConnectionPool::class),
            $this->get(OrderAccessGuard::class),
            new PaymentMethodRegistry([$gateway], $this->get(EventDispatcherInterface::class)),
            $this->get(OrderDataFactory::class),
            $this->get(OrderStatusWriter::class),
            $this->get(OrderRefundClaim::class),
        );

        try {
            $subject->refund(1, $backendUser);
            $this->fail('A declined refund must be reported.');
        } catch (OrderRefundFailedException $exception) {
            $this->assertSame(1791729505, $exception->getCode());
        }

        $this->assertSame('paid', $this->fetchPaymentStatus(1));
    }

    private function fetchPaymentStatus(int $orderUid): string
    {
        return (string)$this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_products_domain_model_order')
            ->select(['payment_status'], 'tx_products_domain_model_order', ['uid' => $orderUid])
            ->fetchOne();
    }
}
