<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Withdrawal;

use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\TraderIdentity;
use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalSettings;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalInstructionVariant;
use TYPO3\CMS\Core\Settings\SettingsInterface;

/**
 * Reads the right-of-withdrawal settings from a site's settings. Controllers pass the settings of the
 * request's site; order mails pass those of the order's site.
 */
final class WithdrawalSettingsFactory
{
    public function fromSettings(SettingsInterface $settings): WithdrawalSettings
    {
        return new WithdrawalSettings(
            periodDays: (int)$this->read($settings, 'products.checkout.withdrawalPeriodDays', 14),
            assumedDeliveryDays: (int)$this->read($settings, 'products.withdrawal.assumedDeliveryDays', 14),
            timezone: (string)$this->read($settings, 'products.withdrawal.timezone', 'Europe/Berlin'),
            returnCostBorneByTrader: (bool)$this->read($settings, 'products.withdrawal.returnCostBorneByTrader', false),
            instructionVariant: WithdrawalInstructionVariant::tryFrom((string)$this->read($settings, 'products.withdrawal.instructionVariant', '')) ?? WithdrawalInstructionVariant::MULTIPLE_GOODS,
            trader: new TraderIdentity(
                name: (string)$this->read($settings, 'products.withdrawal.trader.name', ''),
                address: (string)$this->read($settings, 'products.withdrawal.trader.address', ''),
                email: (string)$this->read($settings, 'products.withdrawal.trader.email', ''),
                phone: (string)$this->read($settings, 'products.withdrawal.trader.phone', ''),
                returnAddress: (string)$this->read($settings, 'products.withdrawal.trader.returnAddress', ''),
            ),
        );
    }

    private function read(SettingsInterface $settings, string $path, mixed $default): mixed
    {
        return $settings->has($path) ? $settings->get($path) : $default;
    }
}
