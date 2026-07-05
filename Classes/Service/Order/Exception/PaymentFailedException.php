<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order\Exception;

final class PaymentFailedException extends \RuntimeException implements OrderPlacementExceptionInterface {}
