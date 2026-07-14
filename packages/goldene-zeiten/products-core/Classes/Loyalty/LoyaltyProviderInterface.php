<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Loyalty;

use GoldeneZeiten\Products\Core\Domain\Dto\Loyalty\LoyaltyContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\CheckoutAdjustment;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contract for a loyalty programme - points, cashback, tiered rewards. Loyalty is on-top functionality,
 * so a shop without one still checks out; the autoconfigure tag collects whatever is registered.
 *
 * Loyalty runs in both directions, which is what sets it apart from a discount. The customer spends on
 * the order being placed, and earns from it, so the contract covers the whole round trip:
 *
 * - {@see quoteRedemption()} values what the customer chose to spend, as a discount adjustment, read-only.
 * - {@see assertRedeemable()} refuses a spend the customer cannot afford, before the order is created.
 * - {@see applyRedemption()} debits the spend and books it, inside the order transaction.
 * - {@see award()} credits what the order earned, once it exists.
 */
#[AutoconfigureTag('products.loyalty_provider')]
interface LoyaltyProviderInterface
{
    public function getIdentifier(): string;

    /**
     * The customer's spendable balance, for the checkout to show. Zero for a programme that does not
     * apply to this customer.
     */
    public function getBalance(LoyaltyContext $context): int;

    /**
     * Refuse a redemption the customer cannot cover, before anything is placed. Does nothing when there
     * is nothing to spend.
     */
    public function assertRedeemable(LoyaltyContext $context): void;

    /**
     * What the requested spend is worth against this order, as a loyalty adjustment. Null when the
     * customer spends nothing here.
     */
    public function quoteRedemption(LoyaltyContext $context): ?CheckoutAdjustment;

    /**
     * Debit the spend and record it against the order. Runs inside the order transaction, so a later
     * failure takes the debit with it.
     */
    public function applyRedemption(Order $order, LoyaltyContext $context): void;

    /**
     * Credit what the order earned. Runs once the order exists.
     */
    public function award(Order $order, LoyaltyContext $context): void;
}
