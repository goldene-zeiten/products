<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Withdrawal\Exception;

/**
 * The plain withdrawal form's order number + email pair did not resolve to an order. Deliberately
 * one exception for "no such order number" and "email does not match" - the form's error message
 * must not let a caller probe which order numbers exist.
 */
final class InvalidWithdrawalRequestException extends \RuntimeException {}
