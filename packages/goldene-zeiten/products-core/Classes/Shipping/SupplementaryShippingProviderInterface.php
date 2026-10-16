<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Shipping;

/**
 * Marks a provider whose options come in addition to the carriers rather than instead of them, e.g.
 * collecting the order in store. The registry always offers its options next to those of whatever carries
 * the basket - a real carrier or the shop's manual fallback - so installing it never hides the shop's own
 * shipping.
 *
 * The `products.shipping_provider` tag is inherited from {@see ShippingProviderInterface}.
 */
interface SupplementaryShippingProviderInterface extends ShippingProviderInterface {}
