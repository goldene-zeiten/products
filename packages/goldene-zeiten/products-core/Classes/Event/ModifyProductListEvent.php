<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Controller\ProductController;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Lets integrators adjust the product list before rendering - filter/reorder the product list,
 * hide out-of-region items, pin promotions, or inject cross-sells.
 * Mutable via {@see ModifyProductListEvent::setProducts()}.
 *
 * @see ProductController::resolveProductsForList()
 */
final class ModifyProductListEvent
{
    /**
     * @param Product[] $products
     */
    public function __construct(
        private array $products,
        private readonly ServerRequestInterface $request
    ) {}

    /**
     * @return Product[]
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    /**
     * @param Product[] $products
     */
    public function setProducts(array $products): void
    {
        $this->products = $products;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
