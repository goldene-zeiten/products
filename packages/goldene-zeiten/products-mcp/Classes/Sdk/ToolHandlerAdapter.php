<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Sdk;

use GoldeneZeiten\Products\Mcp\Exception\ToolFailedException;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;

/**
 * Lets the SDK call a tool of this extension. The tool's array goes back as structured content and as JSON
 * text for clients that only read the text; a {@see ToolFailedException} becomes a tool error result.
 */
final readonly class ToolHandlerAdapter implements ToolHandlerInterface
{
    public function __construct(
        private McpToolInterface $tool,
        private McpContext $context
    ) {}

    public function execute(array $arguments, ClientGateway $gateway): CallToolResult
    {
        try {
            $result = $this->tool->execute($arguments, $this->context);
        } catch (ToolFailedException $exception) {
            throw new ToolCallException($exception->getMessage(), 0, $exception);
        }

        return new CallToolResult([new TextContent($result)], structuredContent: $result);
    }
}
