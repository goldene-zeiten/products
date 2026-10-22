<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Event;

use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpResourceInterface;

/**
 * Lets integrators change which resources an assistant can read from the endpoint of a site - hide a guide or
 * add the shop's own documents. It is dispatched whenever the endpoint builds its server, i.e. on every request.
 * A resource added with {@see addResource()} replaces a registered resource of the same URI.
 *
 * @see McpContext for the site and request the resources are offered for
 */
final class ModifyMcpResourcesEvent
{
    /**
     * @var array<string, McpResourceInterface>
     */
    private array $resources = [];

    /**
     * @param iterable<McpResourceInterface> $resources
     */
    public function __construct(
        iterable $resources,
        private readonly McpContext $context
    ) {
        foreach ($resources as $resource) {
            $this->resources[$resource->getUri()] = $resource;
        }
    }

    /**
     * @return list<McpResourceInterface>
     */
    public function getResources(): array
    {
        return array_values($this->resources);
    }

    public function addResource(McpResourceInterface $resource): void
    {
        $this->resources[$resource->getUri()] = $resource;
    }

    public function removeResource(string $uri): void
    {
        unset($this->resources[$uri]);
    }

    public function getContext(): McpContext
    {
        return $this->context;
    }
}
