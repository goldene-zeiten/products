<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tool;

use GoldeneZeiten\Products\Mcp\Basket\BasketAccess;
use GoldeneZeiten\Products\Mcp\Basket\BasketMapper;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;

/**
 * Shows what an assistant basket holds and what it costs.
 */
final readonly class GetBasketTool implements McpToolInterface
{
    public function __construct(
        private BasketAccess $access,
        private BasketMapper $mapper
    ) {}

    public function getName(): string
    {
        return 'get_basket';
    }

    public function getDescription(): string
    {
        return 'Returns the lines of a basket with their options, quantities and prices, and the totals with '
            . 'the VAT breakdown. Each line has a "line" key to change it with update_basket_item.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'basketToken' => ['type' => 'string', 'description' => 'The token create_basket returned.'],
            ],
            'required' => ['basketToken'],
        ];
    }

    public function execute(array $arguments, McpContext $context): array
    {
        return $this->mapper->map($this->access->open($arguments['basketToken'] ?? null, $context));
    }
}
