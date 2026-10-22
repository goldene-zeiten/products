<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Sdk;

use GoldeneZeiten\Products\Mcp\Event\ModifyMcpResourcesEvent;
use GoldeneZeiten\Products\Mcp\Event\ModifyMcpToolsEvent;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpResourceRegistry;
use GoldeneZeiten\Products\Mcp\Registry\McpToolRegistry;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\Tool;
use Mcp\Server;
use Mcp\Server\Builder;
use Mcp\Server\Session\SessionStoreInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds the SDK server for one request: the registered tools and resources, answered for the site of the request
 * and with the MCP sessions in the given store.
 */
final readonly class McpServerFactory
{
    private const SERVER_VERSION = '1.0.0';

    public function __construct(
        private McpToolRegistry $toolRegistry,
        private McpResourceRegistry $resourceRegistry,
        private LoggerInterface $logger,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function create(McpContext $context, SessionStoreInterface $sessionStore): Server
    {
        $builder = Server::builder()
            ->setServerInfo($this->serverName($context), self::SERVER_VERSION)
            ->setSession($sessionStore)
            ->setLogger($this->logger);
        $this->addTools($builder, $context);
        $this->addResources($builder, $context);

        return $builder->build();
    }

    private function addTools(Builder $builder, McpContext $context): void
    {
        $event = new ModifyMcpToolsEvent($this->toolRegistry->all(), $context);
        $this->eventDispatcher->dispatch($event);
        foreach ($event->getTools() as $tool) {
            $builder->add(
                new Tool($tool->getName(), null, $tool->getInputSchema(), $tool->getDescription(), null),
                new ToolHandlerAdapter($tool, $context, $this->eventDispatcher)
            );
        }
    }

    private function addResources(Builder $builder, McpContext $context): void
    {
        $event = new ModifyMcpResourcesEvent($this->resourceRegistry->all(), $context);
        $this->eventDispatcher->dispatch($event);
        foreach ($event->getResources() as $resource) {
            $builder->add(
                new ResourceDefinition(
                    $resource->getUri(),
                    $resource->getName(),
                    null,
                    $resource->getDescription(),
                    $resource->getMimeType()
                ),
                new ResourceHandlerAdapter($resource, $context)
            );
        }
    }

    private function serverName(McpContext $context): string
    {
        $title = (string)($context->site->getConfiguration()['websiteTitle'] ?? '');

        return $title !== '' ? $title : $context->site->getIdentifier();
    }
}
