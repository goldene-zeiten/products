<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend\Exception;

/**
 * Raised when the payment gateway declined a refund; the order is paid again and may be refunded anew.
 */
final class OrderRefundFailedException extends \RuntimeException {}
