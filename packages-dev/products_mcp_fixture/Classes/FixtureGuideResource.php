<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\McpFixture;

use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpResourceInterface;

/**
 * A resource that is only offered because {@see ModifyMcpResourcesListener} adds it.
 */
final class FixtureGuideResource implements McpResourceInterface
{
    public function getUri(): string
    {
        return 'shop://fixture/guide';
    }

    public function getName(): string
    {
        return 'fixture_guide';
    }

    public function getDescription(): string
    {
        return 'A guide added by an event listener.';
    }

    public function getMimeType(): string
    {
        return 'text/plain';
    }

    public function read(McpContext $context): string
    {
        return 'Added by a listener.';
    }
}
