<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Enum;

enum VoucherDiscountType: string
{
    case PERCENTAGE = 'percentage';
    case FIXED = 'fixed';
}
