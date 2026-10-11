<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal;

use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalItemAvailability;
use GoldeneZeiten\Products\Core\Domain\Model\OrderItem;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * One order item as the withdrawal function presents it: selectable, or shown as exempt, expired or
 * already withdrawn. A null deadline means the withdrawal period has not started to run yet.
 */
#[Exclude]
final readonly class WithdrawalItemState
{
    public function __construct(
        public OrderItem $item,
        public WithdrawalItemAvailability $availability,
        public ?\DateTimeImmutable $deadline,
    ) {}

    public function isSelectable(): bool
    {
        return $this->availability === WithdrawalItemAvailability::WITHDRAWABLE;
    }
}
