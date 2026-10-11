<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express\Exception;

/**
 * Thrown when the amount a wallet settled differs from the total of the order it was meant to pay, so the
 * order is not finalized as paid and the settlement is refunded.
 */
final class ExpressAmountMismatchException extends \RuntimeException implements ExpressOrderExceptionInterface {}
