<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Enum;

enum PaymentResultState: string
{
    case COMPLETED = 'completed';
    case PENDING = 'pending';
    case REDIRECT_REQUIRED = 'redirect_required';
    case FAILED = 'failed';
}
