<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Event;

use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;

/**
 * Lets integrators change which tools an assistant is offered by the endpoint of a site - hide a tool the shop
 * does not want to expose, or add one that is only valid for this site. It is dispatched whenever the endpoint
 * builds its server, i.e. on every request, so the tools of one site never leak into another. A tool added with
 * {@see addTool()} replaces a registered tool of the same name.
 *
 * @see McpContext for the site and request the tools are offered for
 */
final class ModifyMcpToolsEvent
{
    /**
     * @var array<string, McpToolInterface>
     */
    private array $tools = [];

    /**
     * @param iterable<McpToolInterface> $tools
     */
    public function __construct(
        iterable $tools,
        private readonly McpContext $context
    ) {
        foreach ($tools as $tool) {
            $this->tools[$tool->getName()] = $tool;
        }
    }

    /**
     * @return list<McpToolInterface>
     */
    public function getTools(): array
    {
        return array_values($this->tools);
    }

    public function addTool(McpToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function removeTool(string $name): void
    {
        unset($this->tools[$name]);
    }

    public function getContext(): McpContext
    {
        return $this->context;
    }
}
