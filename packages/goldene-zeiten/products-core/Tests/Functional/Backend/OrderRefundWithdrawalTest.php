<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Backend;

use GoldeneZeiten\Products\Core\Backend\Exception\OrderRefundWithheldException;
use GoldeneZeiten\Products\Core\Backend\OrderAccessGuard;
use GoldeneZeiten\Products\Core\Backend\OrderDataFactory;
use GoldeneZeiten\Products\Core\Backend\OrderRefundClaim;
use GoldeneZeiten\Products\Core\Backend\OrderRefundService;
use GoldeneZeiten\Products\Core\Backend\OrderReturnService;
use GoldeneZeiten\Products\Core\Backend\OrderStatusWriter;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\RecordingRefundPaymentMethod;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Art. 13 Directive 2011/83/EU: the refund after a withdrawal covers what was withdrawn, and for sent
 * goods the trader may hold it back until they are received back or proven to be on their way.
 */
final class OrderRefundWithdrawalTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderReturnServiceTest/orders.csv');
    }

    #[Test]
    public function aRefundIsWithheldWhileWithdrawnGoodsAreNeitherReturnedNorProvenOnTheirWay(): void
    {
        $backendUser = $this->backendUser();
        $gateway = new RecordingRefundPaymentMethod();
        $subject = $this->refundServiceWith($gateway);
        $this->expectException(OrderRefundWithheldException::class);
        $this->expectExceptionCode(1791730329);

        try {
            $subject->refund(2, $backendUser);
        } finally {
            $this->assertSame(0, $gateway->refundCalls);
        }
    }

    #[Test]
    public function theRefundCoversTheWholeOrderOnceTheProofOfReturnIsRecordedAndEveryItemWasWithdrawn(): void
    {
        $backendUser = $this->backendUser();
        $gateway = new RecordingRefundPaymentMethod();
        $this->get(OrderReturnService::class)->recordReturnProof(2, $backendUser);

        $this->refundServiceWith($gateway)->refund(2, $backendUser);

        $this->assertSame(5000, $gateway->lastAmount?->getCents());
    }

    #[Test]
    public function theRefundOfAPartialWithdrawalCoversOnlyTheWithdrawnItems(): void
    {
        $backendUser = $this->backendUser();
        $gateway = new RecordingRefundPaymentMethod();

        $this->refundServiceWith($gateway)->refund(5, $backendUser);

        $this->assertSame(2000, $gateway->lastAmount?->getCents());
    }

    #[Test]
    public function aWithdrawalFromAnUnshippedOrderIsRefundedAtOnce(): void
    {
        $backendUser = $this->backendUser();
        $gateway = new RecordingRefundPaymentMethod();

        $this->refundServiceWith($gateway)->refund(4, $backendUser);

        $this->assertSame(2000, $gateway->lastAmount?->getCents());
    }

    #[Test]
    public function aRefundWithoutAnyWithdrawalStillCoversTheWholeOrder(): void
    {
        $backendUser = $this->backendUser();
        $gateway = new RecordingRefundPaymentMethod();

        $this->refundServiceWith($gateway)->refund(6, $backendUser);

        $this->assertSame(5000, $gateway->lastAmount?->getCents());
    }

    private function backendUser(): BackendUserAuthentication
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        return $backendUser;
    }

    private function refundServiceWith(RecordingRefundPaymentMethod $gateway): OrderRefundService
    {
        return new OrderRefundService(
            $this->get(ConnectionPool::class),
            $this->get(OrderAccessGuard::class),
            new PaymentMethodRegistry([$gateway], $this->get(EventDispatcherInterface::class)),
            $this->get(OrderDataFactory::class),
            $this->get(OrderStatusWriter::class),
            $this->get(OrderRefundClaim::class),
        );
    }
}
