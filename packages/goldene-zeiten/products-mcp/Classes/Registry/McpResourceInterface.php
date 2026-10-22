<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Registry;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A document an assistant can read, such as a guide to the shop. Implement it in a service and it is offered by
 * the MCP endpoint of every site that enables it.
 */
#[AutoconfigureTag('products_mcp.resource')]
interface McpResourceInterface
{
    /**
     * The URI the assistant reads the resource by, unique across all resources, for example shop://guide/search.
     */
    public function getUri(): string;

    public function getName(): string;

    public function getDescription(): string;

    public function getMimeType(): string;

    public function read(McpContext $context): string;
}
