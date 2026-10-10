<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Enum;

/**
 * The kinds of money effect a checkout adjustment can have on an order total. Every feature that changes
 * what the customer pays - shipping, fees, discounts, loyalty redemption, deposits - contributes one of
 * these instead of writing into the order itself.
 */
enum AdjustmentType: string
{
    case SHIPPING = 'shipping';
    case HANDLING = 'handling';
    case DISCOUNT = 'discount';
    /**
     * A reduction of a shipping charge - a waived carrier cost - rather than of the goods.
     */
    case SHIPPING_DISCOUNT = 'shipping_discount';
    case LOYALTY = 'loyalty';
    case PAYMENT_FEE = 'payment_fee';
    case DEPOSIT = 'deposit';

    /**
     * Types that reduce what the customer pays, and are reported as the order's discount total.
     */
    public function isReducing(): bool
    {
        return $this->isGoodsReduction() || $this === self::SHIPPING_DISCOUNT;
    }

    /**
     * Types that lower what the customer pays for the goods themselves.
     */
    public function isGoodsReduction(): bool
    {
        return $this === self::DISCOUNT || $this === self::LOYALTY;
    }
}
