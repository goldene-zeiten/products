<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment\Exception;

use GoldeneZeiten\Products\Core\Service\Order\Exception\OrderPlacementExceptionInterface;

final class PaymentMethodNotFoundException extends \RuntimeException implements OrderPlacementExceptionInterface {}
