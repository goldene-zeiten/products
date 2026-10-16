<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Basket\Exception;

/**
 * The options chosen for a line were refused; the message is meant for the customer.
 */
final class LineItemOptionsRejectedException extends \RuntimeException {}
