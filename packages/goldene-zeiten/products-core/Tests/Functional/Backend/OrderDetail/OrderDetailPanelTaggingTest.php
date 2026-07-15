<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Backend\OrderDetail;

use GoldeneZeiten\Products\Core\Backend\OrderDetail\OrderDetailPanelRegistry;
use GoldeneZeiten\Products\ExtensionPointFixture\DummyOrderDetailPanel;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Proves the DI wiring an add-on relies on: a service tagged `products.order_detail_panel` (here the
 * fixture panel) is collected by the registry's TaggedIterator and rendered into the backend order view,
 * receiving the order uid. The registry's own filtering/ordering is unit-tested separately; this proves
 * an externally registered panel is actually discovered.
 */
final class OrderDetailPanelTaggingTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-extension-point-fixture',
    ];

    #[Test]
    public function anExternallyTaggedPanelIsCollectedAndRenderedWithTheOrderUid(): void
    {
        $fragments = $this->get(OrderDetailPanelRegistry::class)->renderPanels(4711);

        $this->assertContains(
            (new DummyOrderDetailPanel())->renderForOrder(4711),
            $fragments,
            'A service tagged products.order_detail_panel must be collected by the registry and passed the order uid.',
        );
    }
}
