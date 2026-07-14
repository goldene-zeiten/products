<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Enum;

enum PaymentStatus: string
{
    case OPEN = 'open';
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::OPEN => in_array($target, [self::PENDING, self::PAID, self::FAILED], true),
            self::PENDING => in_array($target, [self::PAID, self::FAILED], true),
            self::PAID => in_array($target, [self::REFUNDED], true),
            self::FAILED => in_array($target, [self::PENDING, self::PAID], true),
            self::REFUNDED => false,
        };
    }
}
