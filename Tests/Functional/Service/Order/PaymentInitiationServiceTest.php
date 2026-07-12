<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Service\Order\PaymentInitiationService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Tests\Functional\Fixtures\FixturePaymentMethod;
use GoldeneZeiten\Products\Tests\Functional\Fixtures\InvoiceNumberMutatingPaymentMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

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

    /**
     * @param PaymentMethodInterface[] $paymentMethods
     */
    #[Test]
    #[DataProvider('initiateTransactionCountProvider')]
    public function initiateCreatesExpectedTransactionCount(array $paymentMethods, string $resultFixturePath): void
    {
        $subject = $this->get(PaymentInitiationService::class);
        $order = $this->fetchOrder();

        foreach ($paymentMethods as $paymentMethod) {
            $subject->initiate($order, $paymentMethod);
        }

        $this->assertCSVDataSet($resultFixturePath);
    }

    /**
     * @return \Generator<string, array<string, mixed>>
     */
    public static function initiateTransactionCountProvider(): \Generator
    {
        yield 'singleInitiateCreatesOneTransaction' => [
            'paymentMethods' => [FixturePaymentMethod::pending()],
            'resultFixturePath' => __DIR__ . '/Fixtures/Result/payment_transactions_count_1.csv',
        ];
        yield 'repeatedInitiateReusesTheStillOpenTransaction' => [
            'paymentMethods' => [FixturePaymentMethod::pending(), FixturePaymentMethod::pending()],
            'resultFixturePath' => __DIR__ . '/Fixtures/Result/payment_transactions_count_1.csv',
        ];
        yield 'initiateAfterCompletionCreatesANewDistinctAttempt' => [
            'paymentMethods' => [FixturePaymentMethod::completed(), FixturePaymentMethod::completed()],
            'resultFixturePath' => __DIR__ . '/Fixtures/Result/payment_transactions_count_2.csv',
        ];
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
        $subject = $this->get(PaymentInitiationService::class);
        $order = $this->fetchOrder();

        $subject->initiate($order, new InvoiceNumberMutatingPaymentMethod());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/payment_initiation_mutated_invoice_number.csv');
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
