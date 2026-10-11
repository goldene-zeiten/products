<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend\Exception;

/**
 * Raised when the delivery of an order is recorded although the order never left the warehouse.
 */
final class OrderNotShippedException extends \RuntimeException {}
