<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Basket;

use GoldeneZeiten\Products\Core\Service\Basket\BasketStorage;
use GoldeneZeiten\Products\Mcp\Exception\ToolFailedException;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;

/**
 * Opens the basket a tool names by its token.
 */
final readonly class BasketAccess
{
    public function __construct(
        private McpBasketSessions $sessions
    ) {}

    /**
     * @throws ToolFailedException when the token belongs to no basket, or the basket has expired
     */
    public function open(mixed $token, McpContext $context): OpenBasket
    {
        $token = is_string($token) ? $token : '';
        $basket = $this->sessions->find($token, $context->site);
        if ($basket === null) {
            throw new ToolFailedException(
                'There is no basket for this basketToken. It may have expired; create a new one with create_basket.',
                1792711028
            );
        }
        $store = new McpBasketStore($this->sessions, $token, $context->site);

        return new OpenBasket($token, $basket, $context->request->withAttribute(BasketStorage::STORE_ATTRIBUTE, $store));
    }
}
