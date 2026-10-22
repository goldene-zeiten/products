<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Resource;

use GoldeneZeiten\Products\Mcp\Guide\Collector\ShopFactsCollector;
use GoldeneZeiten\Products\Mcp\Guide\GuideRenderer;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;

/**
 * Tells the assistant who runs the shop and how it prices.
 */
final class ShopFactsResource extends AbstractGuideResource
{
    public function __construct(
        GuideRenderer $renderer,
        private readonly ShopFactsCollector $collector
    ) {
        parent::__construct($renderer);
    }

    public function getUri(): string
    {
        return 'shop://shop/facts';
    }

    public function getName(): string
    {
        return 'Shop facts';
    }

    public function getDescription(): string
    {
        return 'Name, URL, currency, VAT handling and the identity of the trader.';
    }

    protected function getTemplateName(): string
    {
        return 'Facts';
    }

    protected function collectVariables(McpContext $context): array
    {
        return $this->collector->collect($context);
    }
}
