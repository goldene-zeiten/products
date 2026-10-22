<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Sdk;

use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpResourceInterface;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ResourceHandlerInterface;

/**
 * Lets the SDK read a resource of this extension.
 */
final readonly class ResourceHandlerAdapter implements ResourceHandlerInterface
{
    public function __construct(
        private McpResourceInterface $resource,
        private McpContext $context
    ) {}

    public function read(string $uri, ClientGateway $gateway): string
    {
        return $this->resource->read($this->context);
    }
}
