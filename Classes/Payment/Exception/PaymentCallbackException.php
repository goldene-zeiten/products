<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Exception;

/**
 * A payment callback could not be attributed to an order, or the payment method it names cannot handle
 * callbacks at all.
 */
final class PaymentCallbackException extends \RuntimeException {}
