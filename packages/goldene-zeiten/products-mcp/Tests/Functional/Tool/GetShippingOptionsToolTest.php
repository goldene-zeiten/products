<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Functional\Tool;

use PHPUnit\Framework\Attributes\Test;

final class GetShippingOptionsToolTest extends AbstractToolTestCase
{
    #[Test]
    public function theOptionsForACountryAndBasketAreQuoted(): void
    {
        $result = $this->callTool('get_shipping_options', [
            'country' => 'DE',
            'items' => [['product' => 1, 'quantity' => 2], ['product' => 3, 'article' => 71, 'quantity' => 1]],
        ]);

        $this->assertFalse($result['isError'], $result['text']);
        $this->assertSame('EUR', $result['data']['currency']);
        $this->assertSame('275.50', $result['data']['goodsTotal']);
        $this->assertSame(
            [['key' => 'tablerate:1', 'label' => 'Standard DE', 'cost' => '4.90', 'deliveryEstimate' => '']],
            $result['data']['options']
        );
    }

    #[Test]
    public function withoutItemsTheEmptyBasketIsQuoted(): void
    {
        $result = $this->callTool('get_shipping_options', ['country' => 'DE']);

        $this->assertFalse($result['isError'], $result['text']);
        $this->assertSame('tablerate:1', $result['data']['options'][0]['key']);
    }

    #[Test]
    public function aCountryNobodyShipsToHasNoOptions(): void
    {
        $this->assertSame([], $this->callTool('get_shipping_options', ['country' => 'FR'])['data']['options']);
    }

    #[Test]
    public function anUnknownOrHiddenProductInTheBasketIsAToolError(): void
    {
        $result = $this->callTool('get_shipping_options', ['country' => 'DE', 'items' => [['product' => 4, 'quantity' => 1]]]);

        $this->assertTrue($result['isError']);
    }
}
