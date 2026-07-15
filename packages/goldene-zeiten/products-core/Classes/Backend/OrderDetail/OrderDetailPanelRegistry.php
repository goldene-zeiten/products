<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend\OrderDetail;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Renders every registered {@see OrderDetailPanelInterface} for an order, dropping the ones that have
 * nothing to show. With no panel registered the order detail simply has no discounts-and-rewards content,
 * which is the correct state for a shop with no add-ons that attach data to orders.
 */
final class OrderDetailPanelRegistry
{
    /**
     * @var OrderDetailPanelInterface[]
     */
    private array $panels;

    /**
     * @param iterable<OrderDetailPanelInterface> $panels
     */
    public function __construct(
        #[TaggedIterator('products.order_detail_panel')]
        iterable $panels
    ) {
        $this->panels = [...$panels];
    }

    /**
     * @return string[] the non-empty rendered panel fragments, in registration order
     */
    public function renderPanels(int $orderUid): array
    {
        $fragments = [];
        foreach ($this->panels as $panel) {
            $html = $panel->renderForOrder($orderUid);
            if ($html !== null && $html !== '') {
                $fragments[] = $html;
            }
        }

        return $fragments;
    }
}
