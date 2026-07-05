<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Exception;

use GoldeneZeiten\Products\Service\Order\Exception\OrderPlacementExceptionInterface;

final class PaymentMethodNotFoundException extends \RuntimeException implements OrderPlacementExceptionInterface {}
