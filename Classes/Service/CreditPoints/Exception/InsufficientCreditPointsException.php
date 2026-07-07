<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\CreditPoints\Exception;

use GoldeneZeiten\Products\Service\Order\Exception\OrderPlacementExceptionInterface;

final class InsufficientCreditPointsException extends \RuntimeException implements OrderPlacementExceptionInterface {}
