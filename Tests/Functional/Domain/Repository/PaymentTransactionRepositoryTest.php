<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\PaymentTransaction;
use GoldeneZeiten\Products\Domain\Repository\PaymentTransactionRepository;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class PaymentTransactionRepositoryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private PaymentTransactionRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/payment_transactions.csv');
        $this->subject = $this->get(PaymentTransactionRepository::class);
    }

    #[Test]
    public function findsAPendingTransactionForTheSameOrderAndMethod(): void
    {
        $transaction = $this->subject->findOneNotYetApproved(100, 'invoice');

        self::assertInstanceOf(PaymentTransaction::class, $transaction);
        self::assertSame('EXT-1', $transaction->getExternalId());
    }

    #[Test]
    public function ignoresAnAlreadyCompletedTransaction(): void
    {
        self::assertNull($this->subject->findOneNotYetApproved(100, 'paypal'));
    }

    #[Test]
    public function findsAFailedTransactionAsNotYetApproved(): void
    {
        $transaction = $this->subject->findOneNotYetApproved(101, 'invoice');

        self::assertInstanceOf(PaymentTransaction::class, $transaction);
        self::assertSame('EXT-3', $transaction->getExternalId());
    }

    #[Test]
    public function returnsNullForAnUnknownOrderMethodPair(): void
    {
        self::assertNull($this->subject->findOneNotYetApproved(999, 'invoice'));
    }

    #[Test]
    public function findsATransactionByItsExternalId(): void
    {
        $transaction = $this->subject->findOneByExternalId('invoice', 'EXT-1');

        self::assertInstanceOf(PaymentTransaction::class, $transaction);
        self::assertSame(100, $transaction->getOrderUid());
    }

    #[Test]
    public function externalIdLookupIsScopedToThePaymentMethod(): void
    {
        self::assertNull($this->subject->findOneByExternalId('paypal', 'EXT-1'));
    }

    #[Test]
    public function emptyExternalIdNeverMatches(): void
    {
        self::assertNull($this->subject->findOneByExternalId('invoice', ''));
    }
}
