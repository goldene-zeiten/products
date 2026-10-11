<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Enum;

enum PaymentStatus: string
{
    case OPEN = 'open';
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case REFUND_PENDING = 'refund_pending';
    case REFUNDED = 'refunded';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::OPEN => in_array($target, [self::PENDING, self::PAID, self::FAILED], true),
            self::PENDING => in_array($target, [self::PAID, self::FAILED], true),
            self::PAID => in_array($target, [self::REFUND_PENDING, self::REFUNDED], true),
            self::REFUND_PENDING => $target === self::REFUNDED,
            self::FAILED => in_array($target, [self::PENDING, self::PAID], true),
            self::REFUNDED => false,
        };
    }

    /**
     * Only a paid order is refunded. A refund the gateway has accepted but not completed yet is not refunded
     * again.
     */
    public function isRefundable(): bool
    {
        return $this === self::PAID;
    }
}
