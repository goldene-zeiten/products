<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Resource;

use GoldeneZeiten\Products\Mcp\Guide\Collector\OrderingGuideCollector;
use GoldeneZeiten\Products\Mcp\Guide\GuideRenderer;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;

/**
 * Explains to the assistant how a customer puts products into the basket in this shop.
 */
final class OrderingGuideResource extends AbstractGuideResource
{
    public function __construct(
        GuideRenderer $renderer,
        private readonly OrderingGuideCollector $collector
    ) {
        parent::__construct($renderer);
    }

    public function getUri(): string
    {
        return 'shop://guide/ordering';
    }

    public function getName(): string
    {
        return 'How to order';
    }

    public function getDescription(): string
    {
        return 'How to choose a variant and a quantity, personalise a product and use a voucher.';
    }

    protected function getTemplateName(): string
    {
        return 'Ordering';
    }

    protected function collectVariables(McpContext $context): array
    {
        return $this->collector->collect($context);
    }
}
