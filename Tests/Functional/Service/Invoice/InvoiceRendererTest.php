<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Invoice;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Invoice\InvoiceRenderer;
use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;

final class InvoiceRendererTest extends AbstractFrontendTestCase
{
    private InvoiceRenderer $subject;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_with_items_and_addresses.csv');
        $this->subject = $this->get(InvoiceRenderer::class);
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        self::assertInstanceOf(Order::class, $order);
        $this->order = $order;
    }

    #[Test]
    public function renderContainsInvoiceAndOrderNumbers(): void
    {
        $html = $this->subject->render($this->order);

        self::assertStringContainsString('INV-1', $html);
        self::assertStringContainsString('ORD-1', $html);
    }

    #[Test]
    public function renderContainsLineItemDetails(): void
    {
        $html = $this->subject->render($this->order);

        self::assertStringContainsString('Red Shoes', $html);
        self::assertStringContainsString('SHOE-RED', $html);
    }

    #[Test]
    public function renderContainsBillingAddress(): void
    {
        $html = $this->subject->render($this->order);

        self::assertStringContainsString('Jane', $html);
        self::assertStringContainsString('Shopper', $html);
        self::assertStringContainsString('Sampletown', $html);
    }

    #[Test]
    public function renderContainsTheGrandTotal(): void
    {
        $html = $this->subject->render($this->order);

        self::assertStringContainsString('100.00 EUR', $html);
    }
}
