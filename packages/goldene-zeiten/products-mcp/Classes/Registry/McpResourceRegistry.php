<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Registry;

use GoldeneZeiten\Products\Mcp\Exception\DuplicateResourceException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * All resources the installed extensions offer.
 */
final class McpResourceRegistry
{
    /**
     * @var array<string, McpResourceInterface>
     */
    private array $resources = [];

    /**
     * @param iterable<McpResourceInterface> $resources
     */
    public function __construct(
        #[TaggedIterator('products_mcp.resource')]
        iterable $resources
    ) {
        foreach ($resources as $resource) {
            $uri = $resource->getUri();
            if (isset($this->resources[$uri])) {
                throw new DuplicateResourceException(sprintf('The MCP resource "%s" is registered twice.', $uri), 1792708326);
            }
            $this->resources[$uri] = $resource;
        }
    }

    /**
     * @return array<string, McpResourceInterface>
     */
    public function all(): array
    {
        return $this->resources;
    }
}
