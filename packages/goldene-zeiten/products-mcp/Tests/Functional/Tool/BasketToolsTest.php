<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Functional\Tool;

use PHPUnit\Framework\Attributes\Test;

final class BasketToolsTest extends AbstractToolTestCase
{
    #[Test]
    public function aBasketIsBuiltAndReadBack(): void
    {
        $token = $this->createBasket();

        $this->assertFalse($this->callTool('add_to_basket', ['basketToken' => $token, 'product' => 1, 'quantity' => 2])['isError']);
        $this->assertFalse($this->callTool('add_to_basket', ['basketToken' => $token, 'product' => 3, 'article' => 71])['isError']);
        $basket = $this->callTool('get_basket', ['basketToken' => $token]);

        $this->assertFalse($basket['isError'], $basket['text']);
        $this->assertSame('EUR', $basket['data']['currency']);
        $this->assertSame(3, $basket['data']['itemCount']);
        $lines = $basket['data']['lines'];
        $this->assertCount(2, $lines);
        $this->assertSame(1, $lines[0]['product']);
        $this->assertSame('Engagement ring', $lines[0]['title']);
        $this->assertNull($lines[0]['article']);
        $this->assertSame(2, $lines[0]['quantity']);
        $this->assertSame('119.00', $lines[0]['unitPrice']['gross']);
        $this->assertSame('238.00', $lines[0]['lineTotal']['gross']);
        $this->assertSame(71, $lines[1]['article']['uid']);
        $this->assertSame('Signet small', $lines[1]['article']['title']);
        $this->assertSame('S1-S', $lines[1]['article']['itemNumber']);
        $this->assertSame('37.50', $lines[1]['lineTotal']['gross']);
        $this->assertSame('275.50', $basket['data']['totals']['gross']);
        $this->assertSame(
            $basket['data']['totals']['gross'],
            number_format((float)$basket['data']['totals']['net'] + (float)$basket['data']['totals']['tax'], 2, '.', '')
        );
        $this->assertSame('19', (string)$basket['data']['vat'][0]['rate']);
    }

    #[Test]
    public function addingTheSameProductAgainRaisesTheLine(): void
    {
        $token = $this->createBasket();
        $this->callTool('add_to_basket', ['basketToken' => $token, 'product' => 1, 'quantity' => 1]);
        $this->callTool('add_to_basket', ['basketToken' => $token, 'product' => 1, 'quantity' => 2]);

        $lines = $this->callTool('get_basket', ['basketToken' => $token])['data']['lines'];

        $this->assertCount(1, $lines);
        $this->assertSame(3, $lines[0]['quantity']);
    }

    #[Test]
    public function moreThanTheStockIsAToolError(): void
    {
        $token = $this->createBasket();
        $this->callTool('add_to_basket', ['basketToken' => $token, 'product' => 1, 'quantity' => 4]);

        $result = $this->callTool('add_to_basket', ['basketToken' => $token, 'product' => 1, 'quantity' => 2]);

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('5', $result['text']);
        $this->assertSame(4, $this->callTool('get_basket', ['basketToken' => $token])['data']['lines'][0]['quantity']);
    }

    #[Test]
    public function aVariantWithUnlimitedStockCanBeAddedInAnyQuantity(): void
    {
        $token = $this->createBasket();

        $result = $this->callTool('add_to_basket', ['basketToken' => $token, 'product' => 3, 'article' => 72, 'quantity' => 100]);

        $this->assertFalse($result['isError'], $result['text']);
    }

    #[Test]
    public function aProductWithVariantsNeedsOne(): void
    {
        $result = $this->callTool('add_to_basket', ['basketToken' => $this->createBasket(), 'product' => 3]);

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('variant', $result['text']);
    }

    #[Test]
    public function aVariantOfAnotherProductIsRefused(): void
    {
        $result = $this->callTool('add_to_basket', ['basketToken' => $this->createBasket(), 'product' => 1, 'article' => 71]);

        $this->assertTrue($result['isError']);
    }

    #[Test]
    public function anUnknownOrHiddenProductIsRefused(): void
    {
        $token = $this->createBasket();

        $this->assertTrue($this->callTool('add_to_basket', ['basketToken' => $token, 'product' => 4])['isError']);
        $this->assertTrue($this->callTool('add_to_basket', ['basketToken' => $token, 'product' => 999])['isError']);
    }

    #[Test]
    public function anUnknownBasketIsAToolError(): void
    {
        $result = $this->callTool('get_basket', ['basketToken' => str_repeat('a', 32)]);

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('basket', $result['text']);
    }

    #[Test]
    public function updatingALineChangesItAndZeroRemovesIt(): void
    {
        $token = $this->createBasket();
        $this->callTool('add_to_basket', ['basketToken' => $token, 'product' => 1, 'quantity' => 1]);
        $line = $this->callTool('get_basket', ['basketToken' => $token])['data']['lines'][0]['line'];

        $update = $this->callTool('update_basket_item', ['basketToken' => $token, 'line' => $line, 'quantity' => 3]);
        $this->assertFalse($update['isError'], $update['text']);
        $this->assertSame(3, $update['data']['lines'][0]['quantity']);

        $this->assertTrue($this->callTool('update_basket_item', ['basketToken' => $token, 'line' => $line, 'quantity' => 6])['isError']);

        $removed = $this->callTool('update_basket_item', ['basketToken' => $token, 'line' => $line, 'quantity' => 0]);
        $this->assertFalse($removed['isError'], $removed['text']);
        $this->assertSame([], $removed['data']['lines']);
    }

    #[Test]
    public function updatingALineNotInTheBasketIsAToolError(): void
    {
        $result = $this->callTool('update_basket_item', ['basketToken' => $this->createBasket(), 'line' => '1:0:', 'quantity' => 1]);

        $this->assertTrue($result['isError']);
    }

    private function createBasket(): string
    {
        $result = $this->callTool('create_basket');
        $this->assertFalse($result['isError'], $result['text']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $result['data']['basketToken']);

        return $result['data']['basketToken'];
    }
}
