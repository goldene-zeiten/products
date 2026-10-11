<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express\Exception;

/**
 * Thrown when an express order could not be placed - an unavailable shipping option, missing stock, an
 * integrator veto, an amount that differs from the order total. The cause is the previous exception. No
 * order exists and a payment that had been settled has been refunded.
 */
final class ExpressOrderFailedException extends \RuntimeException implements ExpressOrderExceptionInterface {}
