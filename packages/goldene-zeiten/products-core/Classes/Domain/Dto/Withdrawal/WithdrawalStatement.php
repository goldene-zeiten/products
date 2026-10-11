<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The online withdrawal statement a consumer submits through the withdrawal function (Art. 11a
 * Directive 2011/83/EU): who is withdrawing, from which items of the order, and when it was submitted.
 */
#[Exclude]
final readonly class WithdrawalStatement
{
    /**
     * @param int[]|null $itemUids order items to withdraw; null means every item that can still be withdrawn
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $reason,
        public ?array $itemUids,
        public \DateTimeImmutable $submittedAt,
    ) {}
}
