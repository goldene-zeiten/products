<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Basket;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketItem;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Basket\BasketStorage;
use GoldeneZeiten\Products\Core\Service\Order\OrderFinalizationService;
use GoldeneZeiten\Products\PaymentFixture\PaymentLifecycleEventRecorder;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Finalization accepts an order once - confirming it, clearing the customer's basket and announcing it -
 * and applies every payment status a gateway reports afterwards. A declined payment must never confirm
 * the order: it cancels it, returns its stock and leaves the customer's basket for another attempt.
 */
final class OrderFinalizationPaymentOutcomeTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-payment-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        PaymentLifecycleEventRecorder::reset();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderFinalizationPaymentOutcomeTest/orders_with_payment_outcomes.csv');
    }

    #[Test]
    public function aPaidResultConfirmsTheOrderAndClearsTheBasket(): void
    {
        $request = $this->requestWithBasket();
        $subject = $this->get(OrderFinalizationService::class);

        $subject->finalize($this->fetchOrder(1), PaymentResult::completed(PaymentStatus::PAID), $request);

        $this->assertSame(['status' => 'confirmed', 'payment_status' => 'paid'], $this->orderRow(1));
        $this->assertTrue($this->get(BasketStorage::class)->load($request)->isEmpty());
        $this->assertSame([1], PaymentLifecycleEventRecorder::$finalizedOrderUids);
    }

    #[Test]
    public function aPendingResultAcceptsTheOrderWithThePaymentStillPending(): void
    {
        $request = $this->requestWithBasket();
        $subject = $this->get(OrderFinalizationService::class);

        $subject->finalize($this->fetchOrder(1), PaymentResult::pending('SEPA-1'), $request);

        $this->assertSame(['status' => 'confirmed', 'payment_status' => 'pending'], $this->orderRow(1));
        $this->assertTrue($this->get(BasketStorage::class)->load($request)->isEmpty());
        $this->assertSame([1], PaymentLifecycleEventRecorder::$finalizedOrderUids);
    }

    #[Test]
    public function aFailedResultCancelsTheOrderInsteadOfConfirmingIt(): void
    {
        $subject = $this->get(OrderFinalizationService::class);

        $subject->finalize($this->fetchOrder(1), PaymentResult::failed('Declined.'), $this->requestWithBasket());

        $this->assertSame(['status' => 'cancelled', 'payment_status' => 'failed'], $this->orderRow(1));
        $this->assertSame([], PaymentLifecycleEventRecorder::$finalizedOrderUids);
    }

    #[Test]
    public function aFailedResultKeepsTheCustomersBasket(): void
    {
        $request = $this->requestWithBasket();
        $subject = $this->get(OrderFinalizationService::class);

        $subject->finalize($this->fetchOrder(1), PaymentResult::failed('Declined.'), $request);

        $this->assertSame(1, $this->get(BasketStorage::class)->load($request)->count());
    }

    #[Test]
    public function aFailedResultReturnsTheOrdersStock(): void
    {
        $subject = $this->get(OrderFinalizationService::class);

        $subject->finalize($this->fetchOrder(1), PaymentResult::failed('Declined.'), $this->requestWithBasket());

        $this->assertSame(5, $this->productStock(1));
    }

    #[Test]
    public function aPaidResultForACancelledOrderRecordsTheMoneyWithoutConfirmingTheOrder(): void
    {
        $subject = $this->get(OrderFinalizationService::class);

        $subject->finalize($this->fetchOrder(2), PaymentResult::completed(PaymentStatus::PAID), $this->requestWithBasket());

        $this->assertSame(['status' => 'cancelled', 'payment_status' => 'paid'], $this->orderRow(2));
        $this->assertSame([], PaymentLifecycleEventRecorder::$finalizedOrderUids);
    }

    #[Test]
    public function aPaidResultForAnAcceptedOrderWithAPendingPaymentMarksItPaid(): void
    {
        $subject = $this->get(OrderFinalizationService::class);

        $subject->finalize($this->fetchOrder(3), PaymentResult::completed(PaymentStatus::PAID), $this->requestWithBasket());

        $this->assertSame(['status' => 'confirmed', 'payment_status' => 'paid'], $this->orderRow(3));
        $this->assertSame(['3:pending->paid'], PaymentLifecycleEventRecorder::$paymentStatusChanges);
        $this->assertSame([], PaymentLifecycleEventRecorder::$finalizedOrderUids);
    }

    #[Test]
    public function aCustomerReturningAfterTheWebhookAcceptedTheOrderStillHasTheirBasketCleared(): void
    {
        $request = $this->requestWithBasket();
        $subject = $this->get(OrderFinalizationService::class);

        $subject->finalize($this->fetchOrder(4), PaymentResult::completed(PaymentStatus::PAID), $request);

        $this->assertTrue($this->get(BasketStorage::class)->load($request)->isEmpty());
    }

    #[Test]
    public function aPendingResultArrivingAfterThePaymentWasPaidDoesNotDowngradeIt(): void
    {
        $subject = $this->get(OrderFinalizationService::class);

        $subject->finalize($this->fetchOrder(4), PaymentResult::pending(), $this->requestWithBasket());

        $this->assertSame(['status' => 'confirmed', 'payment_status' => 'paid'], $this->orderRow(4));
    }

    /**
     * The return and the webhook load the same order concurrently; whichever is second still holds the
     * order as it was before the first one accepted it, and must neither accept it nor announce the
     * payment again.
     */
    #[Test]
    public function aCallbackHoldingAStaleOrderDoesNotAcceptOrAnnounceItASecondTime(): void
    {
        $staleOrder = $this->fetchOrder(1);
        $this->get(ConnectionPool::class)->getConnectionForTable('tx_products_domain_model_order')->update(
            'tx_products_domain_model_order',
            ['status' => 'confirmed', 'payment_status' => 'paid'],
            ['uid' => 1]
        );
        $subject = $this->get(OrderFinalizationService::class);

        $subject->finalize($staleOrder, PaymentResult::completed(PaymentStatus::PAID), $this->requestWithBasket());

        $this->assertSame([], PaymentLifecycleEventRecorder::$finalizedOrderUids);
        $this->assertSame([], PaymentLifecycleEventRecorder::$paymentStatusChanges);
        $this->assertSame(['status' => 'confirmed', 'payment_status' => 'paid'], $this->orderRow(1));
    }

    private function fetchOrder(int $uid): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage($uid);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }

    /**
     * @return array{status: string, payment_status: string}
     */
    private function orderRow(int $uid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_order');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('status', 'payment_status')
            ->from('tx_products_domain_model_order')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        $this->assertIsArray($row);
        return ['status' => (string)$row['status'], 'payment_status' => (string)$row['payment_status']];
    }

    private function productStock(int $uid): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_product');
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->select('in_stock')
            ->from('tx_products_domain_model_product')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }

    private function requestWithBasket(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $request = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser);
        $this->get(BasketStorage::class)->save($request, new Basket([new BasketItem(1, null, 1)]));

        return $request;
    }
}
