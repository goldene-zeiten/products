<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\PaymentTransaction;
use GoldeneZeiten\Products\Domain\Repository\PaymentTransactionRepository;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class PaymentTransactionRepositoryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PaymentTransactionRepositoryTest/payment_transactions.csv');
    }

    #[Test]
    #[DataProvider('findOneNotYetApprovedFindsAMatchProvider')]
    public function findOneNotYetApprovedFindsAMatch(int $orderUid, string $method, string $expectedExternalId): void
    {
        $subject = $this->get(PaymentTransactionRepository::class);

        $transaction = $subject->findOneNotYetApproved($orderUid, $method);

        $this->assertInstanceOf(PaymentTransaction::class, $transaction);
        $this->assertSame($expectedExternalId, $transaction->getExternalId());
    }

    public static function findOneNotYetApprovedFindsAMatchProvider(): \Generator
    {
        yield 'finds a pending transaction for the same order and method' => [
            'orderUid' => 100,
            'method' => 'invoice',
            'expectedExternalId' => 'EXT-1',
        ];

        yield 'finds a failed transaction as not yet approved' => [
            'orderUid' => 101,
            'method' => 'invoice',
            'expectedExternalId' => 'EXT-3',
        ];
    }

    #[Test]
    #[DataProvider('findOneNotYetApprovedReturnsNullProvider')]
    public function findOneNotYetApprovedReturnsNull(int $orderUid, string $method): void
    {
        $subject = $this->get(PaymentTransactionRepository::class);

        $this->assertNull($subject->findOneNotYetApproved($orderUid, $method));
    }

    public static function findOneNotYetApprovedReturnsNullProvider(): \Generator
    {
        yield 'ignores an already completed transaction' => [
            'orderUid' => 100,
            'method' => 'paypal',
        ];

        yield 'returns null for an unknown order/method pair' => [
            'orderUid' => 999,
            'method' => 'invoice',
        ];
    }

    #[Test]
    public function findsATransactionByItsExternalId(): void
    {
        $subject = $this->get(PaymentTransactionRepository::class);

        $transaction = $subject->findOneByExternalId('invoice', 'EXT-1');

        $this->assertInstanceOf(PaymentTransaction::class, $transaction);
        $this->assertSame(100, $transaction->getOrderUid());
    }

    #[Test]
    #[DataProvider('findOneByExternalIdReturnsNullProvider')]
    public function findOneByExternalIdReturnsNull(string $method, string $externalId): void
    {
        $subject = $this->get(PaymentTransactionRepository::class);

        $this->assertNull($subject->findOneByExternalId($method, $externalId));
    }

    public static function findOneByExternalIdReturnsNullProvider(): \Generator
    {
        yield 'external id lookup is scoped to the payment method' => [
            'method' => 'paypal',
            'externalId' => 'EXT-1',
        ];

        yield 'empty external id never matches' => [
            'method' => 'invoice',
            'externalId' => '',
        ];
    }
}
