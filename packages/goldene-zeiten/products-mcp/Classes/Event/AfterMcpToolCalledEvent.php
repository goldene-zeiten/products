<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Event;

use GoldeneZeiten\Products\Mcp\Registry\McpContext;

/**
 * Lets integrators react to a finished tool call - add data to the answer, strip a field or record the call for
 * tracking. The listener sees the arguments the assistant sent and the array the tool returned; what it sets
 * with {@see setResult()} is what the assistant receives. A tool that failed does not dispatch this event.
 */
final class AfterMcpToolCalledEvent
{
    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $result
     */
    public function __construct(
        private readonly string $toolName,
        private readonly array $arguments,
        private array $result,
        private readonly McpContext $context
    ) {}

    public function getToolName(): string
    {
        return $this->toolName;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * @param array<string, mixed> $result
     */
    public function setResult(array $result): void
    {
        $this->result = $result;
    }

    public function getContext(): McpContext
    {
        return $this->context;
    }
}
