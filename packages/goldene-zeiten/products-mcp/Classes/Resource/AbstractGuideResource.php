<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Resource;

use GoldeneZeiten\Products\Mcp\Guide\GuideRenderer;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpResourceInterface;

/**
 * A Markdown document rendered from a Fluid template under Resources/Private/Templates/Guide/. Extend it to add
 * a guide: name the template and collect its variables.
 */
abstract class AbstractGuideResource implements McpResourceInterface
{
    public function __construct(
        private readonly GuideRenderer $renderer
    ) {}

    public function getMimeType(): string
    {
        return 'text/markdown';
    }

    public function read(McpContext $context): string
    {
        return $this->renderer->render('Guide/' . $this->getTemplateName(), $this->collectVariables($context), $context);
    }

    /**
     * The template below the template root path, without extension, such as "Search".
     */
    abstract protected function getTemplateName(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function collectVariables(McpContext $context): array;
}
