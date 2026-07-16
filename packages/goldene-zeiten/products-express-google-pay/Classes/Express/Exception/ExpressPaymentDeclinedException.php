<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\GooglePay\Express\Exception;

/**
 * Thrown when the processor did not approve the Google Pay token, so no order must be created. The confirm
 * endpoint turns it into a declined response the buyer's browser can react to without leaving a half-paid
 * order.
 */
final class ExpressPaymentDeclinedException extends \RuntimeException {}
