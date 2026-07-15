<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Service\Exception;

use GoldeneZeiten\Products\Core\Service\Order\Exception\OrderPlacementExceptionInterface;

final class VoucherRedemptionFailedException extends \RuntimeException implements OrderPlacementExceptionInterface {}
