<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Configuration;

use GoldeneZeiten\Products\Core\Domain\Enum\VoucherDiscountType;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Site Settings read from request's site attribute (not via ConfigurationManagerInterface).
 */
final class GainedVoucherConfigurationFactory
{
    public function create(ServerRequestInterface $request): GainedVoucherConfiguration
    {
        $site = $request->getAttribute('site');
        $settings = $site instanceof Site ? $site->getSettings() : null;
        $rewardType = (string)($settings?->get('products.vouchers.gained.rewardType', 'fixed') ?? 'fixed');

        return new GainedVoucherConfiguration(
            (bool)($settings?->get('products.vouchers.gained.enabled', false) ?? false),
            Money::fromDecimalString((string)($settings?->get('products.vouchers.gained.minimumOrderValue', '0.00') ?? '0.00')),
            VoucherDiscountType::tryFrom($rewardType) ?? VoucherDiscountType::FIXED,
            (string)($settings?->get('products.vouchers.gained.rewardValue', '5.00') ?? '5.00')
        );
    }
}
