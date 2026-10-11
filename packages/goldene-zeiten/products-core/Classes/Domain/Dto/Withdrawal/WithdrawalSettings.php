<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The site settings the right-of-withdrawal rules depend on. Built by
 * {@see \GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalSettingsFactory}.
 */
#[Exclude]
final readonly class WithdrawalSettings
{
    public function __construct(
        public int $periodDays = 14,
        public int $assumedDeliveryDays = 14,
        public string $timezone = 'Europe/Berlin',
        public bool $returnCostBorneByTrader = false,
        public TraderIdentity $trader = new TraderIdentity(),
    ) {}

    public function getDateTimeZone(): \DateTimeZone
    {
        try {
            return new \DateTimeZone($this->timezone);
        } catch (\Exception) {
            return new \DateTimeZone('UTC');
        }
    }
}
