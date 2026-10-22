<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Resource;

use GoldeneZeiten\Products\Mcp\Guide\Collector\SearchGuideCollector;
use GoldeneZeiten\Products\Mcp\Guide\GuideRenderer;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;

/**
 * Explains to the assistant how to find products in this shop.
 */
final class SearchGuideResource extends AbstractGuideResource
{
    public function __construct(
        GuideRenderer $renderer,
        private readonly SearchGuideCollector $collector
    ) {
        parent::__construct($renderer);
    }

    public function getUri(): string
    {
        return 'shop://guide/search';
    }

    public function getName(): string
    {
        return 'How to search';
    }

    public function getDescription(): string
    {
        return 'What the product search covers, which filters exist and how results are paged.';
    }

    protected function getTemplateName(): string
    {
        return 'Search';
    }

    protected function collectVariables(McpContext $context): array
    {
        return $this->collector->collect($context);
    }
}
