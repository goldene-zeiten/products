<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Shipping\Exception;

use GoldeneZeiten\Products\Core\Service\Order\Exception\OrderPlacementExceptionInterface;

/**
 * Shipping is enabled but the customer never chose an option. Checkout sends the customer back to choose
 * one instead of shipping for free.
 */
final class MissingShippingOptionException extends \RuntimeException implements OrderPlacementExceptionInterface {}
