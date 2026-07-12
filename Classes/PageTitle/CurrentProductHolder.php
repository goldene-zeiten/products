<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PageTitle;

use GoldeneZeiten\Products\Domain\Model\Product;

/**
 * Bridges the product controller to {@see ProductPageTitleProvider} via DI's per-request
 * singleton; a PSR-7 request attribute can't carry this across since Extbase's immutable
 * request wrapper never writes back to the frontend request.
 */
final class CurrentProductHolder
{
    private ?Product $product = null;

    public function setProduct(Product $product): void
    {
        $this->product = $product;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }
}
