<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Order\OrderFinalizationService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * The payment transaction is the log of a payment: the row a redirect payment started with ends in the outcome
 * the gateway reported, with the gateway's reference.
 */
final class PaymentTransactionOutcomeTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-payment-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PaymentTransactionOutcomeTest/started_payment.csv');
    }

    #[Test]
    #[DataProvider('outcomeProvider')]
    public function theStartedTransactionEndsInTheReportedOutcome(PaymentResult $result, string $expectedFixture): void
    {
        $subject = $this->get(OrderFinalizationService::class);

        $subject->finalize($this->fetchOrder(), $result, $this->request());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/PaymentTransactionOutcomeTest/Result/' . $expectedFixture);
    }

    public static function outcomeProvider(): \Generator
    {
        yield 'a paid capture' => [
            'result' => PaymentResult::completed(PaymentStatus::PAID, 'CAPTURE-1'),
            'expectedFixture' => 'completed.csv',
        ];
        yield 'a declined payment' => [
            'result' => PaymentResult::failed('Declined.', 'DECLINE-1'),
            'expectedFixture' => 'failed.csv',
        ];
    }

    #[Test]
    public function aReplayedOutcomeAddsNoSecondTransaction(): void
    {
        $subject = $this->get(OrderFinalizationService::class);

        $subject->finalize($this->fetchOrder(), PaymentResult::completed(PaymentStatus::PAID, 'CAPTURE-1'), $this->request());
        $subject->finalize($this->fetchOrder(), PaymentResult::completed(PaymentStatus::PAID, 'CAPTURE-1'), $this->request());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/PaymentTransactionOutcomeTest/Result/completed.csv');
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }

    private function request(): ServerRequest
    {
        return (new ServerRequest('http://localhost/'))->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }
}
