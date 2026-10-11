<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Enum;

/**
 * WITHDRAWN: the customer withdrew from an order that had already left the warehouse; the goods are
 * on their way back and the order stays in this status until the backend marks them returned.
 */
enum OrderStatus: string
{
    case NEW = 'new';
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case SHIPPED = 'shipped';
    case COMPLETED = 'completed';
    case WITHDRAWN = 'withdrawn';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::NEW => in_array($target, [self::PENDING, self::CONFIRMED, self::CANCELLED], true),
            self::PENDING => in_array($target, [self::CONFIRMED, self::CANCELLED], true),
            self::CONFIRMED => in_array($target, [self::SHIPPED, self::CANCELLED], true),
            self::SHIPPED => in_array($target, [self::COMPLETED, self::WITHDRAWN], true),
            self::COMPLETED => $target === self::WITHDRAWN,
            self::WITHDRAWN => in_array($target, [self::COMPLETED, self::CANCELLED], true),
            self::CANCELLED => false,
        };
    }
}
