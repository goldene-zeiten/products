<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order\Exception;

/**
 * Marker for exceptions that a checkout controller can recover from
 * by redirecting the customer back to the review step.
 */
interface OrderPlacementExceptionInterface extends \Throwable {}
