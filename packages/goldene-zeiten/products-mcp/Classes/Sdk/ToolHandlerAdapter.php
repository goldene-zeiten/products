<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Sdk;

use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;

/**
 * Lets the SDK call a tool of this extension and hands the tool's array back as JSON text.
 */
final readonly class ToolHandlerAdapter implements ToolHandlerInterface
{
    public function __construct(
        private McpToolInterface $tool,
        private McpContext $context
    ) {}

    public function execute(array $arguments, ClientGateway $gateway): TextContent
    {
        return new TextContent($this->tool->execute($arguments, $this->context));
    }
}
