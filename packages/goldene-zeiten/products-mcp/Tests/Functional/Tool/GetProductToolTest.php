<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Functional\Tool;

use PHPUnit\Framework\Attributes\Test;

final class GetProductToolTest extends AbstractToolTestCase
{
    #[Test]
    public function aProductComesWithPlainTextManufacturerAndCategories(): void
    {
        $result = $this->callTool('get_product', ['uid' => 1]);

        $this->assertFalse($result['isError'], $result['text']);
        $product = $result['data'];
        $this->assertSame('Engagement ring', $product['title']);
        $this->assertSame('With a diamond', $product['subtitle']);
        $this->assertSame('A fine ring', $product['description']);
        $this->assertSame('119.00', $product['price']['amount']);
        $this->assertSame(['Gold rings'], $product['categories']);
        $this->assertSame(
            ['brand' => 'Stelmach', 'name' => 'Stelmach Trauringe GmbH', 'website' => 'https://stelmach.example.com'],
            $product['manufacturer']
        );
        $this->assertSame([], $product['variants']);
        $this->assertStringContainsString('engagement-ring', $product['url']);
    }

    #[Test]
    public function theVariantsListTheirAttributesPriceAndStock(): void
    {
        $variants = $this->callTool('get_product', ['uid' => 3])['data']['variants'];

        $this->assertCount(2, $variants);
        $this->assertSame(71, $variants[0]['uid']);
        $this->assertSame('Signet small', $variants[0]['title']);
        $this->assertSame(['Size' => 'Small'], $variants[0]['attributes']);
        $this->assertSame('37.50', $variants[0]['price']['amount']);
        $this->assertSame(3, $variants[0]['stock']);
        $this->assertFalse($variants[0]['unlimitedStock']);
        $this->assertTrue($variants[1]['unlimitedStock']);
    }

    #[Test]
    public function aHiddenOrMissingProductIsAToolError(): void
    {
        $hidden = $this->callTool('get_product', ['uid' => 4]);
        $missing = $this->callTool('get_product', ['uid' => 999]);

        $this->assertTrue($hidden['isError']);
        $this->assertStringContainsString('4', $hidden['text']);
        $this->assertTrue($missing['isError']);
    }
}
