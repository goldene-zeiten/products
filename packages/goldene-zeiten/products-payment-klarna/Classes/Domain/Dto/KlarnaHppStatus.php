<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Domain\Dto;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The status of a Klarna Hosted Payment Page session, read back to learn the outcome: when the shopper has
 * completed payment the status is COMPLETED and the authorization token needed to place the order is set.
 */
#[Exclude]
final readonly class KlarnaHppStatus
{
    public function __construct(
        public string $status,
        public string $authorizationToken,
    ) {}

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }

    /**
     * A final state in which the shopper did not pay. Any other non-completed state means the shopper is
     * still on the payment page.
     */
    public function hasFailed(): bool
    {
        return in_array($this->status, ['CANCELLED', 'FAILED', 'ERROR', 'DISABLED'], true);
    }
}
