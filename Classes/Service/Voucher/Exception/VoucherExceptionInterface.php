<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Voucher\Exception;

/**
 * Marker for exceptions a basket/checkout controller can recover from by showing the shopper
 * the reason and letting them continue without the voucher.
 */
interface VoucherExceptionInterface extends \Throwable {}
