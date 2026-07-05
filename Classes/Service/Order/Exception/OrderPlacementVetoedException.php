<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order\Exception;

final class OrderPlacementVetoedException extends \RuntimeException implements OrderPlacementExceptionInterface {}
