<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Shipping\Exception;

use GoldeneZeiten\Products\Core\Service\Order\Exception\OrderPlacementExceptionInterface;

/**
 * No carrier can ship this basket to this address. Checkout must stop: an order nobody can deliver must
 * not be payable.
 */
final class NoShippingOptionAvailableException extends \RuntimeException implements OrderPlacementExceptionInterface {}
