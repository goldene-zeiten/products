<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Shipping;

use GoldeneZeiten\Products\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\Shipping\HandlingFeeService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class HandlingFeeServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private Product $lightProduct;
    private Product $heavyProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/handling_fees.csv');
        $productRepository = $this->get(ProductRepository::class);
        $lightProduct = $productRepository->findByUid(1);
        $heavyProduct = $productRepository->findByUid(2);
        self::assertInstanceOf(Product::class, $lightProduct);
        self::assertInstanceOf(Product::class, $heavyProduct);
        $this->lightProduct = $lightProduct;
        $this->heavyProduct = $heavyProduct;
    }

    #[Test]
    public function resolveCostIsZeroWhenDisabled(): void
    {
        $cost = $this->get(HandlingFeeService::class)->resolveCost($this->configuration(false), $this->basketViewModel($this->lightProduct), 'DE');

        self::assertSame(0, $cost->getCents());
    }

    #[Test]
    public function resolveCostPicksTheApplicableFeeForALightBasket(): void
    {
        $cost = $this->get(HandlingFeeService::class)->resolveCost($this->configuration(true), $this->basketViewModel($this->lightProduct), 'DE');

        self::assertSame(300, $cost->getCents());
    }

    #[Test]
    public function resolveCostPicksTheApplicableFeeForAHeavyBasket(): void
    {
        $cost = $this->get(HandlingFeeService::class)->resolveCost($this->configuration(true), $this->basketViewModel($this->heavyProduct), 'DE');

        self::assertSame(900, $cost->getCents());
    }

    #[Test]
    public function resolveCostIsZeroWhenNothingApplies(): void
    {
        $cost = $this->get(HandlingFeeService::class)->resolveCost($this->configuration(true), $this->basketViewModel($this->lightProduct), 'FR');

        self::assertSame(0, $cost->getCents());
    }

    private function configuration(bool $enabled): ProductsConfiguration
    {
        return new ProductsConfiguration('DE', 'gross', 'EUR', false, Money::fromCents(0), $enabled, 'none');
    }

    private function basketViewModel(Product $product): BasketViewModel
    {
        $unitPrice = $product->getPrice();
        $item = new BasketViewItem($product, null, 1, $unitPrice, $unitPrice, 0.0, $unitPrice, $unitPrice, Money::fromCents(0));
        return new BasketViewModel([$item], $unitPrice, $unitPrice, Money::fromCents(0), 'EUR');
    }
}
