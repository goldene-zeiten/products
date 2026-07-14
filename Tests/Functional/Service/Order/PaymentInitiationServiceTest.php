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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * Regression: resubmitted initiate() calls should reuse still-open transactions.
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
        $request = $this->request();

        foreach ($paymentMethods as $paymentMethod) {
            $subject->initiate($order, $paymentMethod, $request);
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
     * Regression: Extbase won't auto-flush mutations on fetched entities without explicit update().
     */
    #[Test]
    public function initiateFlushesOrderMutationsMadeByThePaymentMethod(): void
    {
        $subject = $this->get(PaymentInitiationService::class);
        $order = $this->fetchOrder();
        $request = $this->request();

        $subject->initiate($order, new InvoiceNumberMutatingPaymentMethod(), $request);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/payment_initiation_mutated_invoice_number.csv');
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }

    private function request(): ServerRequestInterface
    {
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }
}
