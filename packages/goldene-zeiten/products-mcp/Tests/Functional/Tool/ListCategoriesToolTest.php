<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Functional\Tool;

use PHPUnit\Framework\Attributes\Test;

final class ListCategoriesToolTest extends AbstractToolTestCase
{
    #[Test]
    public function theTopLevelCategoriesComeWithChildrenFlagAndProductCount(): void
    {
        $categories = $this->callTool('list_categories')['data']['categories'];

        $this->assertSame([1, 3], array_column($categories, 'uid'));
        $this->assertSame('Rings', $categories[0]['title']);
        $this->assertTrue($categories[0]['hasChildren']);
        $this->assertSame(1, $categories[0]['productCount']);
        $this->assertFalse($categories[1]['hasChildren']);
        $this->assertSame(1, $categories[1]['productCount']);
    }

    #[Test]
    public function aParentListsItsChildren(): void
    {
        $categories = $this->callTool('list_categories', ['parent' => 1])['data']['categories'];

        $this->assertSame([2], array_column($categories, 'uid'));
        $this->assertSame(1, $categories[0]['productCount']);
    }

    #[Test]
    public function anUnknownParentIsAToolError(): void
    {
        $this->assertTrue($this->callTool('list_categories', ['parent' => 999])['isError']);
    }
}
