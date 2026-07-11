<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\PaymentTransaction;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Domain\Repository\PaymentTransactionRepository;
use GoldeneZeiten\Products\Payment\PaymentContextFactory;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Service\Order\PaymentInitiationService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

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

    private PaymentInitiationService $subject;
    private PaymentTransactionRepository $paymentTransactionRepository;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/orders_with_frontend_user.csv');
        $this->paymentTransactionRepository = $this->get(PaymentTransactionRepository::class);
        $this->subject = new PaymentInitiationService(
            $this->get(PaymentContextFactory::class),
            $this->paymentTransactionRepository,
            $this->get(PersistenceManagerInterface::class)
        );
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        self::assertNotNull($order);
        $this->order = $order;
    }

    #[Test]
    public function initiateCreatesOneTransaction(): void
    {
        $this->subject->initiate($this->order, $this->pendingPaymentMethod());

        self::assertCount(1, $this->allTransactions());
    }

    #[Test]
    public function repeatedInitiateReusesTheStillOpenTransactionInstead(): void
    {
        $this->subject->initiate($this->order, $this->pendingPaymentMethod());
        $this->subject->initiate($this->order, $this->pendingPaymentMethod());

        self::assertCount(1, $this->allTransactions());
    }

    #[Test]
    public function initiateAfterCompletionCreatesANewDistinctAttempt(): void
    {
        $this->subject->initiate($this->order, $this->completedPaymentMethod());
        $this->subject->initiate($this->order, $this->completedPaymentMethod());

        self::assertCount(2, $this->allTransactions());
    }

    /**
     * @return PaymentTransaction[]
     */
    private function allTransactions(): array
    {
        return iterator_to_array($this->paymentTransactionRepository->findAll());
    }

    private function pendingPaymentMethod(): PaymentMethodInterface
    {
        return $this->fakePaymentMethod(PaymentResult::pending('EXT-1'));
    }

    private function completedPaymentMethod(): PaymentMethodInterface
    {
        return $this->fakePaymentMethod(PaymentResult::completed(PaymentStatus::PENDING, 'EXT-2'));
    }

    private function fakePaymentMethod(PaymentResult $result): PaymentMethodInterface
    {
        return new class ($result) implements PaymentMethodInterface {
            public function __construct(private readonly PaymentResult $result) {}

            public function getIdentifier(): string
            {
                return 'fake';
            }

            public function getLabel(): string
            {
                return 'Fake';
            }

            public function isAvailable(PaymentContext $context): bool
            {
                return true;
            }

            public function calculateFee(PaymentContext $context): int
            {
                return 0;
            }

            public function initiate(Order $order, PaymentContext $context): PaymentResult
            {
                return $this->result;
            }
        };
    }
}
