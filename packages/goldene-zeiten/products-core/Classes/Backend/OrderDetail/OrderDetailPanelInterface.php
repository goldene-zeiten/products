<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend\OrderDetail;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes a panel to the "discounts and rewards" area of the backend order detail view. Add-ons that
 * attach their own data to an order - loyalty ledgers, voucher redemptions, and the like - render it here
 * without the core order module knowing about them. Each panel renders its own self-contained HTML fragment
 * so it can bring whatever columns it needs.
 */
#[AutoconfigureTag('products.order_detail_panel')]
interface OrderDetailPanelInterface
{
    /**
     * Rendered HTML for this order's panel, or null when this panel has nothing to show for the order.
     */
    public function renderForOrder(int $orderUid): ?string;
}
