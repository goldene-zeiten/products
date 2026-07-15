<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Shipping;

/**
 * Marks a carrier as the shop's own built-in fallback rather than a real carrier: the manual shipping the
 * shop maintains itself. The registry offers a fallback carrier's options only when no real carrier can
 * serve the basket, so installing a carrier extension takes over automatically and uninstalling it hands
 * shipping back to the manual fallback - never leaving the customer with no option to choose.
 *
 * The `products.shipping_provider` tag is inherited from {@see ShippingProviderInterface}, so a fallback
 * carrier registers the same way; this only distinguishes it from a real carrier at selection time.
 */
interface FallbackShippingProviderInterface extends ShippingProviderInterface {}
