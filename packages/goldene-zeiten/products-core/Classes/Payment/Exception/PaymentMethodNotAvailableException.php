<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment\Exception;

use GoldeneZeiten\Products\Core\Service\Order\Exception\OrderPlacementExceptionInterface;

/**
 * The chosen payment method is not one the shop offers for this basket and customer. Checkout sends the
 * customer back to choose again.
 */
final class PaymentMethodNotAvailableException extends \RuntimeException implements OrderPlacementExceptionInterface {}
