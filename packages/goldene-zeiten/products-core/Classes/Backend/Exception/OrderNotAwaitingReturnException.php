<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend\Exception;

/**
 * Raised when a return step is attempted on an order that is not waiting for withdrawn goods to come back.
 */
final class OrderNotAwaitingReturnException extends \RuntimeException {}
