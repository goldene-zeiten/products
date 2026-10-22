<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Unit\Event;

use GoldeneZeiten\Products\Mcp\Event\ModifyMcpToolsEvent;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Tool\ShopInfoTool;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ModifyMcpToolsEventTest extends UnitTestCase
{
    #[Test]
    public function aToolCanBeRemovedByName(): void
    {
        $event = $this->eventWithShopInfo();

        $event->removeTool('shop_info');

        $this->assertSame([], $event->getTools());
    }

    #[Test]
    public function aToolAddedWithTheNameOfARegisteredToolReplacesIt(): void
    {
        $event = $this->eventWithShopInfo();
        $replacement = new ShopInfoTool();

        $event->addTool($replacement);

        $this->assertSame([$replacement], $event->getTools());
    }

    private function eventWithShopInfo(): ModifyMcpToolsEvent
    {
        $context = new McpContext(new Site('shop', 1, []), $this->createStub(ServerRequestInterface::class));

        return new ModifyMcpToolsEvent([new ShopInfoTool()], $context);
    }
}
