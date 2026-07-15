<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched once a visitor has been shown a product's detail page. Add-ons that track viewing history
 * (recently viewed, most viewed) listen for this instead of the core detail controller calling them
 * directly, so the core does not depend on any tracking feature being installed.
 *
 * @see \GoldeneZeiten\Products\Core\Controller\ProductController::showAction()
 */
final class ProductViewedEvent
{
    public function __construct(
        private readonly Product $product,
        private readonly ServerRequestInterface $request
    ) {}

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
