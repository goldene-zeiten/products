<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Basket;

use GoldeneZeiten\Products\Core\Domain\Dto\Basket;
use Psr\Http\Message\ServerRequestInterface;

/**
 * An assistant basket found by its token: its content, and the request to hand to the shop's basket services
 * so they work on this basket.
 */
final readonly class OpenBasket
{
    public function __construct(
        public string $token,
        public Basket $basket,
        public ServerRequestInterface $request
    ) {}
}
