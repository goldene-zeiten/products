<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend\Exception;

/**
 * Raised when a backend user lacks the permission an order operation requires.
 */
final class OrderAccessDeniedException extends \RuntimeException {}
