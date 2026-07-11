<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\PaymentTransaction;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Domain\Repository\PaymentTransactionRepository;
use GoldeneZeiten\Products\Service\Order\PaymentInitiationService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Tests\Functional\Fixtures\FixturePaymentMethod;
use GoldeneZeiten\Products\Tests\Functional\Fixtures\InvoiceNumberMutatingPaymentMethod;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Regression coverage for a real (fixed) bug: PaymentInitiationService unconditionally inserted a
 * new PaymentTransaction on every initiate() call - a resubmitted checkout/double-click/timeout
 * retry created duplicate audit rows for the same order/method pair instead of reusing the still-
 * open one.
 */
final class PaymentInitiationServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/orders_with_frontend_user.csv');
    }

    #[Test]
    public function initiateCreatesOneTransaction(): void
    {
        $this->get(PaymentInitiationService::class)->initiate($this->fetchOrder(), FixturePaymentMethod::pending());

        $this->assertCount(1, $this->allTransactions());
    }

    #[Test]
    public function repeatedInitiateReusesTheStillOpenTransactionInstead(): void
    {
        $subject = $this->get(PaymentInitiationService::class);
        $order = $this->fetchOrder();

        $subject->initiate($order, FixturePaymentMethod::pending());
        $subject->initiate($order, FixturePaymentMethod::pending());

        $this->assertCount(1, $this->allTransactions());
    }

    #[Test]
    public function initiateAfterCompletionCreatesANewDistinctAttempt(): void
    {
        $subject = $this->get(PaymentInitiationService::class);
        $order = $this->fetchOrder();

        $subject->initiate($order, FixturePaymentMethod::completed());
        $subject->initiate($order, FixturePaymentMethod::completed());

        $this->assertCount(2, $this->allTransactions());
    }

    /**
     * Regression coverage: a payment method's initiate() (InvoicePaymentMethod::initiate() being
     * the shipped example) is allowed to mutate the $order it's handed - here setting an invoice
     * number - but $order was fetched via the repository, not freshly add()ed, so Extbase never
     * auto-flushes that mutation on persistAll() without an explicit update() first. Asserted via
     * a raw column read, not a re-fetch through the repository, since Extbase's identity map would
     * return the same in-memory (already-mutated) object and mask a persistence gap.
     */
    #[Test]
    public function initiateFlushesOrderMutationsMadeByThePaymentMethod(): void
    {
        $order = $this->fetchOrder();

        $this->get(PaymentInitiationService::class)->initiate($order, new InvoiceNumberMutatingPaymentMethod());

        $this->assertSame(InvoiceNumberMutatingPaymentMethod::INVOICE_NUMBER, $this->persistedInvoiceNumber($order));
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }

    /**
     * @return PaymentTransaction[]
     */
    private function allTransactions(): array
    {
        return iterator_to_array($this->get(PaymentTransactionRepository::class)->findAll());
    }

    private function persistedInvoiceNumber(Order $order): string
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_order');
        return (string)$queryBuilder
            ->select('invoice_number')
            ->from('tx_products_domain_model_order')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($order->getUid(), Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }
}
