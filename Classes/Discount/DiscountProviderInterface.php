<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Discount;

use GoldeneZeiten\Products\Core\Domain\Dto\Discount\DiscountContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\CheckoutAdjustment;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contract for anything that lowers what the customer pays - vouchers, campaign rules, customer-group
 * rebates. Discounts are on-top functionality, so a shop without one still checks out; the autoconfigure
 * tag on this interface collects whatever is registered.
 *
 * The two phases are kept apart because they run at different times. {@see quote()} only computes what
 * the discount is worth, and is safe to call whenever a total has to be shown. {@see apply()} performs
 * the booking that must not happen more than once - marking a voucher used, writing a redemption row -
 * and runs inside the order transaction, so a later failure unwinds it.
 */
#[AutoconfigureTag('products.discount_provider')]
interface DiscountProviderInterface
{
    public function getIdentifier(): string;

    /**
     * Higher priority runs first. Since a discount can offset an adjustment an earlier provider added,
     * order matters; providers sharing a priority keep their registration order.
     */
    public function getPriority(): int;

    /**
     * Compute the discount for this context, as one or more adjustments. Read-only: no voucher is marked
     * used here, so it may be called to display a total as often as needed. An empty array means the
     * discount does not apply.
     *
     * @return CheckoutAdjustment[]
     */
    public function quote(DiscountContext $context): array;

    /**
     * Book the discount against the placed order - the write that must happen exactly once. Runs inside
     * the order transaction; throwing rolls the whole placement back.
     */
    public function apply(Order $order, DiscountContext $context): void;
}
