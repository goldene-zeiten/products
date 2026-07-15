<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Enum;

enum OrderStatus: string
{
    case NEW = 'new';
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case SHIPPED = 'shipped';
    case COMPLETED = 'completed';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::NEW => in_array($target, [self::PENDING, self::CONFIRMED, self::CANCELLED], true),
            self::PENDING => in_array($target, [self::CONFIRMED, self::CANCELLED], true),
            self::CONFIRMED => in_array($target, [self::SHIPPED, self::CANCELLED], true),
            self::SHIPPED => in_array($target, [self::COMPLETED], true),
            self::CANCELLED, self::COMPLETED => false,
        };
    }
}
