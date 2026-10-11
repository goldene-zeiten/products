<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend\Exception;

/**
 * Raised when a refund is attempted while the trader may still withhold it, because the withdrawn goods are neither received back nor proven to be on their way.
 */
final class OrderRefundWithheldException extends \RuntimeException {}
