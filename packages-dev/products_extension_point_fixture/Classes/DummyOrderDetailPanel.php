<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ExtensionPointFixture;

use GoldeneZeiten\Products\Core\Backend\OrderDetail\OrderDetailPanelInterface;

/**
 * A dummy order-detail panel proving an externally registered panel is tag-collected by
 * {@see \GoldeneZeiten\Products\Core\Backend\OrderDetail\OrderDetailPanelRegistry} and handed the order
 * uid. It echoes the uid back so a test can assert the registry both found it and passed the right order.
 */
final class DummyOrderDetailPanel implements OrderDetailPanelInterface
{
    public function renderForOrder(int $orderUid): string
    {
        return sprintf('<div class="fixture-panel">Fixture panel for order %d</div>', $orderUid);
    }
}
