<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Functional\Tool;

use PHPUnit\Framework\Attributes\Test;

final class SearchProductsToolTest extends AbstractToolTestCase
{
    #[Test]
    public function aSearchTermFindsTheProductWithItsPriceBrandAndCategory(): void
    {
        $result = $this->callTool('search_products', ['query' => 'Engagement']);

        $this->assertFalse($result['isError'], $result['text']);
        $this->assertSame(1, $result['data']['total']);
        $item = $result['data']['items'][0];
        $this->assertSame(1, $item['uid']);
        $this->assertSame('Engagement ring', $item['title']);
        $this->assertSame('R1', $item['itemNumber']);
        $this->assertSame('119.00', $item['price']['amount']);
        $this->assertTrue($item['price']['includesVat']);
        $this->assertSame('EUR', $item['price']['currency']);
        $this->assertFalse($item['price']['from']);
        $this->assertTrue($item['inStock']);
        $this->assertSame('Stelmach', $item['manufacturer']);
        $this->assertSame('Gold rings', $item['category']);
        $this->assertStringStartsWith('http://localhost/shop', $item['url']);
        $this->assertStringContainsString('engagement-ring', $item['url']);
    }

    #[Test]
    public function aVariantProductIsPricedFromItsCheapestVariant(): void
    {
        $item = $this->callTool('search_products', ['query' => 'Signet'])['data']['items'][0];

        $this->assertSame('37.50', $item['price']['amount']);
        $this->assertTrue($item['price']['from']);
        $this->assertTrue($item['inStock']);
    }

    #[Test]
    public function aHiddenProductIsNeverReturned(): void
    {
        $result = $this->callTool('search_products', ['query' => 'Hidden']);

        $this->assertSame(0, $result['data']['total']);
        $this->assertSame([], $result['data']['items']);
    }

    #[Test]
    public function theCategoryFilterIncludesTheSubcategories(): void
    {
        $result = $this->callTool('search_products', ['query' => '', 'category' => 1]);

        $this->assertSame([1, 3], array_column($result['data']['items'], 'uid'));
    }

    #[Test]
    public function theManufacturerAndPriceFiltersNarrowTheResult(): void
    {
        $this->assertSame([1], array_column($this->callTool('search_products', ['manufacturer' => 1])['data']['items'], 'uid'));
        $this->assertSame([2], array_column($this->callTool('search_products', ['maxPrice' => 50, 'query' => 'chain'])['data']['items'], 'uid'));
        $this->assertSame([1], array_column($this->callTool('search_products', ['minPrice' => 100])['data']['items'], 'uid'));
        $this->assertSame([2, 3], array_column($this->callTool('search_products', ['maxPrice' => 40])['data']['items'], 'uid'));
        $this->assertSame([3], array_column($this->callTool('search_products', ['minPrice' => 40, 'maxPrice' => 50])['data']['items'], 'uid'));
    }

    #[Test]
    public function thePageSizeIsCappedAndTheResultIsPaged(): void
    {
        $capped = $this->callTool('search_products', ['perPage' => 500])['data'];
        $this->assertSame(50, $capped['perPage']);

        $second = $this->callTool('search_products', ['perPage' => 1, 'page' => 2])['data'];
        $this->assertSame(3, $second['total']);
        $this->assertSame(2, $second['page']);
        $this->assertCount(1, $second['items']);
    }
}
