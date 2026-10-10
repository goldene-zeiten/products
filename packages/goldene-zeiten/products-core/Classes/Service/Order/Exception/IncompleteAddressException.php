<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order\Exception;

/**
 * The billing or delivery address lacks a required field or has an invalid email. Checkout sends the
 * customer back to the address step.
 */
final class IncompleteAddressException extends \RuntimeException implements OrderPlacementExceptionInterface {}
