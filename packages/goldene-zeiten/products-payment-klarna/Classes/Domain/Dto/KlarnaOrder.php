<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Domain\Dto;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The result of placing a Klarna order against an authorization token: the order id and Klarna's fraud
 * decision, which decides whether the money is taken (ACCEPTED), held for review (PENDING) or refused
 * (REJECTED).
 */
#[Exclude]
final readonly class KlarnaOrder
{
    public function __construct(
        public string $orderId,
        public string $fraudStatus,
    ) {}

    public function isAccepted(): bool
    {
        return $this->fraudStatus === 'ACCEPTED';
    }

    public function isPending(): bool
    {
        return $this->fraudStatus === 'PENDING';
    }
}
