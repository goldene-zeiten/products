<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Backend\OrderDetail;

use GoldeneZeiten\Products\Core\Backend\OrderDetail\OrderDetailPanelInterface;
use GoldeneZeiten\Products\Core\Backend\OrderDetail\OrderDetailPanelRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrderDetailPanelRegistryTest extends TestCase
{
    #[Test]
    public function noPanelsMeansNoContent(): void
    {
        $this->assertSame([], (new OrderDetailPanelRegistry([]))->renderPanels(1));
    }

    #[Test]
    public function collectsOnlyTheNonEmptyFragmentsInOrder(): void
    {
        $registry = new OrderDetailPanelRegistry([
            $this->panel('<div>A</div>'),
            $this->panel(null),
            $this->panel(''),
            $this->panel('<div>B</div>'),
        ]);

        $this->assertSame(['<div>A</div>', '<div>B</div>'], $registry->renderPanels(1));
    }

    private function panel(?string $fragment): OrderDetailPanelInterface
    {
        return new class ($fragment) implements OrderDetailPanelInterface {
            public function __construct(private readonly ?string $fragment) {}

            public function renderForOrder(int $orderUid): ?string
            {
                return $this->fragment;
            }
        };
    }
}
