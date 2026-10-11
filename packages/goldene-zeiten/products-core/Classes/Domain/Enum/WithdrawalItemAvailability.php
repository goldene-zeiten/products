<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Enum;

/**
 * Whether the consumer can still withdraw from a single order item.
 */
enum WithdrawalItemAvailability: string
{
    case WITHDRAWABLE = 'withdrawable';
    case EXEMPT = 'exempt';
    case EXPIRED = 'expired';
    case WITHDRAWN = 'withdrawn';
}
