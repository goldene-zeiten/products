<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PageTitle;

use GoldeneZeiten\Products\Domain\Model\Product;

/**
 * Bridges ProductController::showAction() (an uncached/USER_INT action, per the article
 * price/stock variant-switching feature) to ProductPageTitleProvider, which runs later in the same
 * request during TypoScriptFrontendController's title-generation pass. A PSR-7 request attribute
 * cannot carry this across: Extbase's own request wraps the frontend one, is immutable, and never
 * writes back into the frontend request instance the title-generation pass later receives. A plain
 * DI-resolved service instance, by contrast, is already a de-facto per-request singleton (the
 * Symfony container is shared for the whole request), which is all that's needed here.
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
