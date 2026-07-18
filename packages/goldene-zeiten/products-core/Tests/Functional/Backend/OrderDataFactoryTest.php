<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Backend;

use GoldeneZeiten\Products\Core\Backend\OrderDataFactory;
use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderData;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers {@see OrderDataFactory}: an order row, its addresses and its line items are reshaped into the
 * {@see OrderData} aggregate.
 */
final class OrderDataFactoryTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderDataFactoryTest/order_aggregate.csv');
    }

    #[Test]
    public function buildMapsTheOrderScalarsAndMoneyTotals(): void
    {
        $order = $this->get(OrderDataFactory::class)->build(1);

        $this->assertInstanceOf(OrderData::class, $order);
        $this->assertSame(1, $order->uid);
        $this->assertSame('ORD-77', $order->orderNumber);
        $this->assertSame('carol@example.com', $order->email);
        $this->assertSame('EUR', $order->currency);
        $this->assertSame('INV-77', $order->invoiceNumber);
        $this->assertSame('invoice', $order->paymentMethod);
        $this->assertSame(OrderStatus::CONFIRMED, $order->status);
        $this->assertSame(PaymentStatus::PAID, $order->paymentStatus);
        $this->assertSame(2000, $order->totalGross->getCents());
        $this->assertSame(1680, $order->totalNet->getCents());
        $this->assertSame(500, $order->discountTotal->getCents());
        $this->assertSame('DE', $order->taxCountry);
        $this->assertSame(['19' => 320], $order->taxBreakdown);
        $this->assertSame('confirmed', $order->statusLog[0]['to']);
    }

    #[Test]
    public function buildMapsBothAddresses(): void
    {
        $order = $this->get(OrderDataFactory::class)->build(1);

        $this->assertNotNull($order);
        $this->assertNotNull($order->billingAddress);
        $this->assertSame('Baker', $order->billingAddress->lastName);
        $this->assertSame('ACME', $order->billingAddress->company);
        $this->assertNotNull($order->deliveryAddress);
        $this->assertSame('Miller', $order->deliveryAddress->lastName);
    }

    #[Test]
    public function buildMapsTheLineItems(): void
    {
        $order = $this->get(OrderDataFactory::class)->build(1);

        $this->assertNotNull($order);
        $this->assertCount(2, $order->items);
        $this->assertSame('First Item', $order->items[0]->title);
        $this->assertSame(2, $order->items[0]->quantity);
        $this->assertSame(500, $order->items[0]->unitPriceGross->getCents());
        $this->assertSame(19.0, $order->items[0]->taxRate);
        $this->assertSame('Second Item', $order->items[1]->title);
    }

    #[Test]
    public function buildReturnsNullForAnUnknownOrder(): void
    {
        $this->assertNull($this->get(OrderDataFactory::class)->build(999));
    }

    #[Test]
    public function buildReturnsNullForADeletedOrder(): void
    {
        $this->assertNull($this->get(OrderDataFactory::class)->build(2));
    }
}
