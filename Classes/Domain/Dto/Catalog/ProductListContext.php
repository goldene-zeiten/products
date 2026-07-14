<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Catalog;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What a product-list mode has to work from. It carries the request so a mode can resolve whatever it
 * depends on - the current customer, site settings - itself, rather than the product controller having
 * to know what each mode needs.
 */
#[Exclude]
final readonly class ProductListContext
{
    public function __construct(
        private ServerRequestInterface $request
    ) {}

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
