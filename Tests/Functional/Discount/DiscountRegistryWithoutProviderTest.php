<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Discount;

use GoldeneZeiten\Products\Core\Discount\DiscountContextFactory;
use GoldeneZeiten\Products\Core\Discount\DiscountRegistry;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class DiscountRegistryWithoutProviderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
    ];

    #[Test]
    public function registryHandlesEmptyContextWithoutError(): void
    {
        $registry = $this->get(DiscountRegistry::class);
        $factory = $this->get(DiscountContextFactory::class);

        $basket = $this->basketViewModel('100.00');
        // No codes applied, so the shipped voucher provider (the only one without the fixture)
        // should contribute nothing
        $context = $factory->createFromBasket($basket, 1, [], new AdjustmentCollection());

        $adjustments = $registry->collect($context);

        // The shipped voucher provider contributes nothing when no codes are entered
        $this->assertSame([], $adjustments);
    }

    private function basketViewModel(string $unitPriceGross): BasketViewModel
    {
        $gross = Money::fromDecimalString($unitPriceGross);
        $item = new BasketViewItem(new Product(), null, 1, $gross, $gross, 0.0, $gross, $gross, Money::fromCents(0));
        return new BasketViewModel([$item], $gross, $gross, Money::fromCents(0), 'EUR');
    }
}
