<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The model withdrawal form of Annex I(B) of Directive 2011/83/EU, in the language it was built for and
 * addressed to the trader.
 */
#[Exclude]
final readonly class WithdrawalModelForm
{
    public function __construct(
        public string $heading,
        public string $lead,
        public string $to,
        public string $notice,
        public string $ordered,
        public string $name,
        public string $address,
        public string $signature,
        public string $date,
        public string $delete,
    ) {}
}
