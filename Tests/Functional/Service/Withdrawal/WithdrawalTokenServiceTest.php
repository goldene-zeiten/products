<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Withdrawal;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Withdrawal\WithdrawalTokenService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class WithdrawalTokenServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_with_items_and_addresses.csv');
    }

    #[Test]
    public function aGeneratedTokenIsValidForTheSameOrder(): void
    {
        $subject = $this->get(WithdrawalTokenService::class);
        $order = $this->fetchOrder();

        $token = $subject->generateToken($order);

        $this->assertTrue($subject->isValid($order, $token));
    }

    #[Test]
    public function aTamperedTokenIsRejected(): void
    {
        $subject = $this->get(WithdrawalTokenService::class);
        $order = $this->fetchOrder();

        $token = $subject->generateToken($order);

        $this->assertFalse($subject->isValid($order, $token . 'x'));
    }

    #[Test]
    public function anEmptyTokenIsRejected(): void
    {
        $this->assertFalse($this->get(WithdrawalTokenService::class)->isValid($this->fetchOrder(), ''));
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
