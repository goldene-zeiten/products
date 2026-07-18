<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend\Exception;

/**
 * Raised when the DataHandler datamap that persists an order status transition reports an error, so a
 * failed backend write never passes silently.
 */
final class OrderStatusWriteFailedException extends \RuntimeException {}
