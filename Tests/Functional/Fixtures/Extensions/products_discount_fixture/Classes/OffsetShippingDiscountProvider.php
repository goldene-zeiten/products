<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\DiscountFixture;

use GoldeneZeiten\Products\Discount\DiscountProviderInterface;
use GoldeneZeiten\Products\Domain\Dto\Discount\DiscountContext;
use GoldeneZeiten\Products\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\ValueObject\CheckoutAdjustment;
use GoldeneZeiten\Products\Domain\ValueObject\CoreAdjustmentProvider;
use GoldeneZeiten\Products\Domain\ValueObject\Money;

/**
 * Fixture discount provider that offers free shipping when the 'FREESHIP' code is applied.
 * It finds and offsets the shipping adjustment that shipping already produced, without calling
 * into shipping itself. Proves the "a later provider offsets an earlier adjustment it can see"
 * mechanism with an EXTERNAL provider.
 */
final class OffsetShippingDiscountProvider implements DiscountProviderInterface
{
    public function getIdentifier(): string
    {
        return 'fixture-freeship';
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function quote(DiscountContext $context): array
    {
        if (!in_array('FREESHIP', $context->getAppliedCodes(), true)) {
            return [];
        }

        // Find all shipping adjustments from the core provider and sum them
        $shippingTotal = Money::fromCents(0);
        foreach ($context->getAccumulatedAdjustments()->byType(AdjustmentType::SHIPPING) as $adjustment) {
            if ($adjustment->getProviderIdentifier() === CoreAdjustmentProvider::SHIPPING) {
                $shippingTotal = $shippingTotal->add($adjustment->getAmount());
            }
        }

        // If there's shipping to offset, negate it
        if ($shippingTotal->getCents() === 0) {
            return [];
        }

        return [
            new CheckoutAdjustment(
                AdjustmentType::DISCOUNT,
                'fixture-freeship',
                'Free Shipping',
                Money::fromCents(-$shippingTotal->getCents())
            ),
        ];
    }

    public function apply(Order $order, DiscountContext $context): void
    {
        // No-op: this dummy books nothing.
    }
}
