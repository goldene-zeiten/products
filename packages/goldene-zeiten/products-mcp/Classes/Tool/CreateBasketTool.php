<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tool;

use GoldeneZeiten\Products\Mcp\Basket\McpBasketSessions;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;

/**
 * Starts an empty basket for the assistant to fill. It lives on the server and is not the browser basket of
 * any customer; the customer gets its content through the link of start_checkout.
 */
final readonly class CreateBasketTool implements McpToolInterface
{
    public function __construct(
        private McpBasketSessions $sessions
    ) {}

    public function getName(): string
    {
        return 'create_basket';
    }

    public function getDescription(): string
    {
        return 'Creates an empty basket and returns its basketToken, which the other basket tools ask for. '
            . 'Keep the token for the whole conversation.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
        ];
    }

    public function execute(array $arguments, McpContext $context): array
    {
        return ['basketToken' => $this->sessions->create($context->site)];
    }
}
