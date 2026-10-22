<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\McpFixture;

use GoldeneZeiten\Products\Mcp\Event\ModifyMcpResourcesEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class ModifyMcpResourcesListener
{
    public function __invoke(ModifyMcpResourcesEvent $event): void
    {
        $event->addResource(new FixtureGuideResource());
    }
}
