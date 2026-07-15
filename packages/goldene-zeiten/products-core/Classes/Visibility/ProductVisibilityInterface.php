<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Visibility;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contract for deciding whether a product is visible to the current visitor - gate the catalogue by
 * frontend user group, region, feature flag, or a per-customer assortment. Registering an
 * implementation is how integrators hide products from both the list and the detail view.
 *
 * Aggregation is an explicit allow/deny contract, deliberately not a mutable event: with no
 * implementation registered every product is visible (today's behaviour); once one or more are
 * registered a single {@see ProductVisibilityInterface::isVisible()} returning false hides the
 * product - deny wins over allow, so a checker can never be silently bypassed the way a forgotten
 * event listener could.
 *
 * @see ProductVisibilityResolver::isVisible()
 */
#[AutoconfigureTag('products.product_visibility')]
interface ProductVisibilityInterface
{
    /**
     * Determine if a product is visible to the current visitor.
     */
    public function isVisible(Product $product, ServerRequestInterface $request): bool;
}
