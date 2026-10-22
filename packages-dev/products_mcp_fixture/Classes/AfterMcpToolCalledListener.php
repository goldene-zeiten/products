<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\McpFixture;

use GoldeneZeiten\Products\Mcp\Event\AfterMcpToolCalledEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class AfterMcpToolCalledListener
{
    public function __invoke(AfterMcpToolCalledEvent $event): void
    {
        if ($event->getToolName() !== 'shop_info') {
            return;
        }
        $event->setResult([...$event->getResult(), 'rewrittenBy' => 'listener', 'argumentCount' => count($event->getArguments())]);
    }
}
