<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\GooglePay\Payment\Exception;

/**
 * Thrown when the payment processor cannot be reached or answers unusably while authorizing the Google Pay
 * token.
 */
final class GooglePayProcessorException extends \RuntimeException {}
