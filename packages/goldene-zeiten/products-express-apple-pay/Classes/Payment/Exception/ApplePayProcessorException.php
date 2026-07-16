<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\ApplePay\Payment\Exception;

/**
 * Thrown when the payment processor cannot be reached or answers unusably while validating the merchant
 * session or authorizing the Apple Pay token.
 */
final class ApplePayProcessorException extends \RuntimeException {}
