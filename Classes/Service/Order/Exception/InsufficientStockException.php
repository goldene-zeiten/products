<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order\Exception;

final class InsufficientStockException extends \RuntimeException implements OrderPlacementExceptionInterface {}
