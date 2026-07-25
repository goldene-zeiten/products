<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order\Exception;

final class WithdrawalWaiverNotAcceptedException extends \RuntimeException implements OrderPlacementExceptionInterface {}
