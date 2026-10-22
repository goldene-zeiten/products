<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Registry;

use GoldeneZeiten\Products\Mcp\Exception\DuplicateToolException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * All tools the installed extensions offer.
 */
final class McpToolRegistry
{
    /**
     * @var array<string, McpToolInterface>
     */
    private array $tools = [];

    /**
     * @param iterable<McpToolInterface> $tools
     */
    public function __construct(
        #[TaggedIterator('products_mcp.tool')]
        iterable $tools
    ) {
        foreach ($tools as $tool) {
            $name = $tool->getName();
            if (isset($this->tools[$name])) {
                throw new DuplicateToolException(sprintf('The MCP tool "%s" is registered twice.', $name), 1792708325);
            }
            $this->tools[$name] = $tool;
        }
    }

    /**
     * @return array<string, McpToolInterface>
     */
    public function all(): array
    {
        return $this->tools;
    }
}
