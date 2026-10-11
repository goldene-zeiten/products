<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\Stripe\Express\Exception;

use GoldeneZeiten\Products\Core\Express\Exception\ExpressOrderExceptionInterface;

/**
 * Thrown when the Stripe PaymentIntent for an express checkout was not settled - a declined card or an
 * intent that ended in any state other than succeeded. No order is created.
 */
final class ExpressPaymentDeclinedException extends \RuntimeException implements ExpressOrderExceptionInterface {}
