<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Unit\Registry;

use GoldeneZeiten\Products\Mcp\Exception\DuplicateToolException;
use GoldeneZeiten\Products\Mcp\Registry\McpToolRegistry;
use GoldeneZeiten\Products\Mcp\Tool\ShopInfoTool;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class McpToolRegistryTest extends UnitTestCase
{
    #[Test]
    public function toolsAreIndexedByName(): void
    {
        $registry = new McpToolRegistry([new ShopInfoTool()]);

        $this->assertSame(['shop_info'], array_keys($registry->all()));
    }

    #[Test]
    public function aToolNameRegisteredTwiceIsRejected(): void
    {
        $this->expectException(DuplicateToolException::class);

        new McpToolRegistry([new ShopInfoTool(), new ShopInfoTool()]);
    }
}
