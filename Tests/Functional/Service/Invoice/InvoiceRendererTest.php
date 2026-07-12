<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Invoice;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Invoice\InvoiceRenderer;
use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class InvoiceRendererTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_with_items_and_addresses.csv');
    }

    #[Test]
    #[DataProvider('renderedContentProvider')]
    public function renderIncludesExpectedContent(string $expectedSubstring1, string $expectedSubstring2): void
    {
        $subject = $this->get(InvoiceRenderer::class);

        $html = $subject->render($this->fetchOrder());

        $this->assertStringContainsString($expectedSubstring1, $html);
        $this->assertStringContainsString($expectedSubstring2, $html);
    }

    public static function renderedContentProvider(): \Generator
    {
        yield 'invoice and order numbers' => ['expectedSubstring1' => 'INV-1', 'expectedSubstring2' => 'ORD-1'];
        yield 'line item details' => ['expectedSubstring1' => 'Red Shoes', 'expectedSubstring2' => 'SHOE-RED'];
        yield 'billing address' => ['expectedSubstring1' => 'Jane', 'expectedSubstring2' => 'Shopper'];
        yield 'grand total' => ['expectedSubstring1' => 'Sampletown', 'expectedSubstring2' => '100.00 EUR'];
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
