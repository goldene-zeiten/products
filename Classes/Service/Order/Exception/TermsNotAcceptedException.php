<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order\Exception;

final class TermsNotAcceptedException extends \RuntimeException implements OrderPlacementExceptionInterface {}
