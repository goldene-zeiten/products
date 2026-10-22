<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Registry;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A tool an assistant can call. Implement it in a service and it is offered by the MCP endpoint of every site
 * that enables it.
 */
#[AutoconfigureTag('products_mcp.tool')]
interface McpToolInterface
{
    /**
     * The name the assistant calls the tool by, unique across all tools.
     */
    public function getName(): string;

    public function getDescription(): string;

    /**
     * JSON Schema of the arguments, an object schema.
     *
     * @return array<string, mixed>
     */
    public function getInputSchema(): array;

    /**
     * @param array<string, mixed> $arguments Validated against the input schema
     * @return array<string, mixed> Returned to the assistant as JSON
     */
    public function execute(array $arguments, McpContext $context): array;
}
