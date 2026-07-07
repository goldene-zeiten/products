<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Shipping\Exception;

use GoldeneZeiten\Products\Service\Order\Exception\OrderPlacementExceptionInterface;

final class NoShippingMethodAvailableException extends \RuntimeException implements OrderPlacementExceptionInterface {}
