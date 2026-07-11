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
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_with_items_and_addresses.csv');
    }

    #[Test]
    public function renderContainsInvoiceAndOrderNumbers(): void
    {
        $html = $this->get(InvoiceRenderer::class)->render($this->fetchOrder());

        $this->assertStringContainsString('INV-1', $html);
        $this->assertStringContainsString('ORD-1', $html);
    }

    #[Test]
    public function renderContainsLineItemDetails(): void
    {
        $html = $this->get(InvoiceRenderer::class)->render($this->fetchOrder());

        $this->assertStringContainsString('Red Shoes', $html);
        $this->assertStringContainsString('SHOE-RED', $html);
    }

    #[Test]
    public function renderContainsBillingAddress(): void
    {
        $html = $this->get(InvoiceRenderer::class)->render($this->fetchOrder());

        $this->assertStringContainsString('Jane', $html);
        $this->assertStringContainsString('Shopper', $html);
        $this->assertStringContainsString('Sampletown', $html);
    }

    #[Test]
    public function renderContainsTheGrandTotal(): void
    {
        $html = $this->get(InvoiceRenderer::class)->render($this->fetchOrder());

        $this->assertStringContainsString('100.00 EUR', $html);
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
