<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Functional\Event;

use GoldeneZeiten\Products\Mcp\Tests\Functional\Tool\AbstractToolTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integrators reshape what an assistant sees through PSR-14 events. The listeners of the MCP fixture package
 * remove a tool, add a tool and a resource and extend the result of a tool call.
 */
final class McpEventsTest extends AbstractToolTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-mcp',
        'goldene-zeiten/products-mcp-fixture',
    ];

    #[Test]
    public function aToolRemovedByAListenerIsNotListed(): void
    {
        $names = array_column($this->requestResult('tools/list')['tools'], 'name');

        $this->assertNotContains('list_manufacturers', $names);
        $this->assertContains('search_products', $names);
    }

    #[Test]
    public function aToolAddedByAListenerIsListedAndCallable(): void
    {
        $names = array_column($this->requestResult('tools/list')['tools'], 'name');
        $this->assertContains('fixture_ping', $names);

        $result = $this->callTool('fixture_ping');

        $this->assertFalse($result['isError']);
        $this->assertSame(['ping' => 'pong'], $result['data']);
    }

    #[Test]
    public function aToolResultChangedByAListenerReachesTheClient(): void
    {
        $result = $this->callTool('shop_info');

        $this->assertSame('Demo Shop', $result['data']['name']);
        $this->assertSame('listener', $result['data']['rewrittenBy']);
        $this->assertSame(0, $result['data']['argumentCount']);
        $this->assertSame('listener', json_decode($result['text'], true)['rewrittenBy']);
    }

    #[Test]
    public function aResourceAddedByAListenerIsListedAndReadable(): void
    {
        $uris = array_column($this->requestResult('resources/list')['resources'], 'uri');
        $this->assertContains('shop://fixture/guide', $uris);

        $contents = $this->requestResult('resources/read', ['uri' => 'shop://fixture/guide'])['contents'];

        $this->assertSame('Added by a listener.', $contents[0]['text']);
    }
}
