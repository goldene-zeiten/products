<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\DiscountFixture;

use GoldeneZeiten\Products\Core\Discount\DiscountProviderInterface;
use GoldeneZeiten\Products\Core\Domain\Dto\Discount\DiscountContext;
use GoldeneZeiten\Products\Core\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\CheckoutAdjustment;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;

/**
 * Fixture discount provider with a flat 5 EUR discount when the 'FLAT5' code is applied.
 * Proves an EXTERNAL discount provider reaches the order total through the contract, and
 * that it reads the context (only applies with the code).
 */
final class FlatFiveEuroDiscountProvider implements DiscountProviderInterface
{
    public function getIdentifier(): string
    {
        return 'fixture-flat5';
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function quote(DiscountContext $context): array
    {
        if (!in_array('FLAT5', $context->getAppliedCodes(), true)) {
            return [];
        }

        return [
            new CheckoutAdjustment(
                AdjustmentType::DISCOUNT,
                'fixture-flat5',
                'Flat 5 EUR off',
                Money::fromCents(-500)
            ),
        ];
    }

    public function apply(Order $order, DiscountContext $context): void
    {
        // No-op: this dummy books nothing.
    }
}
