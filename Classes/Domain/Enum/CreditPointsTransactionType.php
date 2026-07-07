<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Enum;

enum CreditPointsTransactionType: string
{
    case EARN = 'earn';
    case REDEEM = 'redeem';
    case ADJUSTMENT = 'adjustment';
}
