<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\McpFixture;

use GoldeneZeiten\Products\Mcp\Event\ModifyMcpToolsEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class ModifyMcpToolsListener
{
    public function __invoke(ModifyMcpToolsEvent $event): void
    {
        $event->removeTool('list_manufacturers');
        $event->addTool(new FixturePingTool());
    }
}
