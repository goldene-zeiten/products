<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express\Exception;

/**
 * Thrown when an express order is confirmed for a basket that turned out to be empty - the basket was
 * cleared or expired between the button opening and the wallet confirming. No order is created.
 */
final class EmptyExpressBasketException extends \RuntimeException {}
