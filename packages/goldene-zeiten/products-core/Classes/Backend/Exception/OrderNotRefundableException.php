<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend\Exception;

/**
 * Raised before any gateway call when an order cannot be refunded: its payment status does not allow
 * it, or its payment method has no refund.
 */
final class OrderNotRefundableException extends \RuntimeException {}
