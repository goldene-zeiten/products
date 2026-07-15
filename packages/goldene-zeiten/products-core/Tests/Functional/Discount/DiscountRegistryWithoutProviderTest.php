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
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;

final class DiscountRegistryWithoutProviderTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function registryHandlesEmptyContextWithoutError(): void
    {
        $registry = $this->get(DiscountRegistry::class);
        $factory = $this->get(DiscountContextFactory::class);

        $basket = $this->basketViewModel('100.00');
        // With no discount provider registered (voucher lives in an add-on that is not loaded here),
        // the registry has nothing to collect.
        $context = $factory->createFromBasket($basket, 1, new ServerRequest('http://localhost/'), new AdjustmentCollection());

        $adjustments = $registry->collect($context);

        // No providers, so no adjustments
        $this->assertSame([], $adjustments);
    }

    private function basketViewModel(string $unitPriceGross): BasketViewModel
    {
        $gross = Money::fromDecimalString($unitPriceGross);
        $item = new BasketViewItem(new Product(), null, 1, $gross, $gross, 0.0, $gross, $gross, Money::fromCents(0));
        return new BasketViewModel([$item], $gross, $gross, Money::fromCents(0), 'EUR');
    }
}
