<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Discount;

use GoldeneZeiten\Products\Core\Discount\DiscountContextFactory;
use GoldeneZeiten\Products\Core\Discount\DiscountRegistry;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
use GoldeneZeiten\Products\Core\Domain\ValueObject\CheckoutAdjustment;
use GoldeneZeiten\Products\Core\Domain\ValueObject\CoreAdjustmentProvider;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class DiscountRegistryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-discount-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DiscountRegistryTest/vouchers.csv');
    }

    #[Test]
    public function fixtureProviderIsCollectedViaTag(): void
    {
        $registry = $this->get(DiscountRegistry::class);
        $factory = $this->get(DiscountContextFactory::class);

        $basket = $this->basketViewModel('100.00');
        $context = $factory->createFromBasket($basket, 1, ['FLAT5'], new AdjustmentCollection());

        $adjustments = $registry->collect($context);

        // The fixture provider should be in the adjustments (the voucher provider is also present)
        $fixtureFlat5Adjustments = array_filter(
            $adjustments,
            static fn(CheckoutAdjustment $adj): bool => $adj->getProviderIdentifier() === 'fixture-flat5'
        );
        $this->assertCount(1, $fixtureFlat5Adjustments);
        $fixtureAdj = array_values($fixtureFlat5Adjustments)[0];
        $this->assertSame(-500, $fixtureAdj->getAmount()->getCents());
    }

    #[Test]
    public function contextIsHonoured(): void
    {
        $registry = $this->get(DiscountRegistry::class);
        $factory = $this->get(DiscountContextFactory::class);

        $basket = $this->basketViewModel('100.00');
        // No codes applied, so the flat-5 discount should not trigger
        $context = $factory->createFromBasket($basket, 1, [], new AdjustmentCollection());

        $adjustments = $registry->collect($context);

        // Should have adjustments from other providers (e.g., voucher), but NOT from fixture-flat5
        $fixtureFlat5Adjustments = array_filter(
            $adjustments,
            static fn(CheckoutAdjustment $adj): bool => $adj->getProviderIdentifier() === 'fixture-flat5'
        );
        $this->assertCount(0, $fixtureFlat5Adjustments);
    }

    #[Test]
    public function offsetMechanismWorks(): void
    {
        $registry = $this->get(DiscountRegistry::class);
        $factory = $this->get(DiscountContextFactory::class);

        $basket = $this->basketViewModel('100.00');

        // Build an accumulated AdjustmentCollection containing a SHIPPING adjustment
        $shippingAdjustment = new CheckoutAdjustment(
            AdjustmentType::SHIPPING,
            CoreAdjustmentProvider::SHIPPING,
            'Shipping Cost',
            Money::fromCents(595),
            0.19
        );
        $accumulated = new AdjustmentCollection($shippingAdjustment);

        $context = $factory->createFromBasket($basket, 1, ['FREESHIP'], $accumulated);

        $adjustments = $registry->collect($context);

        // Should have a free shipping discount that negates the 595 cents
        $freeShipAdjustments = array_filter(
            $adjustments,
            static fn(CheckoutAdjustment $adj): bool => $adj->getProviderIdentifier() === 'fixture-freeship'
        );
        $this->assertCount(1, $freeShipAdjustments);
        $freeShipAdj = array_values($freeShipAdjustments)[0];
        $this->assertSame(-595, $freeShipAdj->getAmount()->getCents());
    }

    private function basketViewModel(string $unitPriceGross): BasketViewModel
    {
        $gross = Money::fromDecimalString($unitPriceGross);
        $item = new BasketViewItem(new Product(), null, 1, $gross, $gross, 0.0, $gross, $gross, Money::fromCents(0));
        return new BasketViewModel([$item], $gross, $gross, Money::fromCents(0), 'EUR');
    }
}
