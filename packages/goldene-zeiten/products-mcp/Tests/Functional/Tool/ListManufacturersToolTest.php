<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Functional\Tool;

use PHPUnit\Framework\Attributes\Test;

final class ListManufacturersToolTest extends AbstractToolTestCase
{
    #[Test]
    public function manufacturersWithVisibleProductsAreListed(): void
    {
        $manufacturers = $this->callTool('list_manufacturers')['data']['manufacturers'];

        $this->assertSame(
            [['uid' => 1, 'brand' => 'Stelmach', 'name' => 'Stelmach Trauringe GmbH', 'website' => 'https://stelmach.example.com']],
            $manufacturers
        );
    }

    #[Test]
    public function aCategoryNarrowsTheListToItsSubtree(): void
    {
        $this->assertCount(1, $this->callTool('list_manufacturers', ['category' => 1])['data']['manufacturers']);
        $this->assertSame([], $this->callTool('list_manufacturers', ['category' => 3])['data']['manufacturers']);
    }
}
