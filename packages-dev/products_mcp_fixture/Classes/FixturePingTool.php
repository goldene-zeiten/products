<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\McpFixture;

use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;

/**
 * A tool that is only offered because {@see ModifyMcpToolsListener} adds it.
 */
final class FixturePingTool implements McpToolInterface
{
    public function getName(): string
    {
        return 'fixture_ping';
    }

    public function getDescription(): string
    {
        return 'Answers pong.';
    }

    public function getInputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function execute(array $arguments, McpContext $context): array
    {
        return ['ping' => 'pong'];
    }
}
