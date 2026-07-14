<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Catalog;

use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductListContext;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A product listing an integrator can add and an editor can place - "products you can afford with your
 * points", "clearance", "staff picks". It is more than a query: it is a view the editor chooses in the
 * product-list content element, so a provider supplies both the products and the label the choice shows.
 *
 * The extension's own listings (all, offers, highlights, new) stay built in; this is the seam for the
 * ones a feature adds. The autoconfigure tag collects whatever is registered.
 */
#[AutoconfigureTag('products.product_list_mode')]
interface ProductListModeProviderInterface
{
    /**
     * The stored value that selects this mode - what the content element records and the controller
     * dispatches on.
     */
    public function getMode(): string;

    /**
     * The label the editor sees when choosing this listing.
     */
    public function getLabel(): string;

    /**
     * @return Product[] the products this listing shows for the given context
     */
    public function findProducts(ProductListContext $context): array;
}
