<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Shipping;

use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\Repository\ShippingMethodRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\Shipping\Exception\NoShippingMethodAvailableException;
use GoldeneZeiten\Products\Service\Shipping\ShippingCostService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

final class ShippingCostServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private Product $lightProduct;
    private Product $heavyProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/shipping_methods.csv');
        $productRepository = $this->get(ProductRepository::class);
        $lightProduct = $productRepository->findByUid(1);
        $heavyProduct = $productRepository->findByUid(2);
        self::assertInstanceOf(Product::class, $lightProduct);
        self::assertInstanceOf(Product::class, $heavyProduct);
        $this->lightProduct = $lightProduct;
        $this->heavyProduct = $heavyProduct;
    }

    #[Test]
    public function countrySpecificMethodsTakePrecedenceOverTheFallback(): void
    {
        $methods = $this->subject(true)->resolveAvailable($this->basketViewModel($this->lightProduct, 1), 'DE');

        $titles = array_map(static fn($method): string => $method->getTitle(), $methods);
        self::assertContains('Standard DE', $titles);
        self::assertNotContains('Fallback', $titles);
    }

    #[Test]
    public function theFallbackIsUsedWhenNoCountrySpecificMethodExists(): void
    {
        $methods = $this->subject(true)->resolveAvailable($this->basketViewModel($this->lightProduct, 1), 'AT');

        self::assertCount(1, $methods);
        self::assertSame('Fallback', $methods[0]->getTitle());
    }

    #[Test]
    public function aBasketAboveTheWeightTierGetsOnlyTheHeavyMethod(): void
    {
        $methods = $this->subject(true)->resolveAvailable($this->basketViewModel($this->heavyProduct, 1), 'DE');

        $titles = array_map(static fn($method): string => $method->getTitle(), $methods);
        self::assertContains('Heavy DE', $titles);
        self::assertNotContains('Standard DE', $titles);
    }

    #[Test]
    public function aBasketBelowTheMinimumOrderValueExcludesThatMethod(): void
    {
        $methods = $this->subject(true)->resolveAvailable($this->basketViewModel($this->lightProduct, 1), 'DE');

        $titles = array_map(static fn($method): string => $method->getTitle(), $methods);
        self::assertNotContains('Big Orders Only DE', $titles);
    }

    #[Test]
    public function nothingIsAvailableWhenTheFeatureIsDisabled(): void
    {
        $methods = $this->subject(false)->resolveAvailable($this->basketViewModel($this->lightProduct, 1), 'DE');

        self::assertSame([], $methods);
    }

    #[Test]
    public function resolveSelectionReturnsNoneWhenTheFeatureIsDisabled(): void
    {
        $selection = $this->subject(false)->resolveSelection(1, $this->basketViewModel($this->lightProduct, 1), 'DE', false);

        self::assertSame(0, $selection->getShippingMethodUid());
        self::assertSame(0, $selection->getCost()->getCents());
    }

    #[Test]
    public function resolveSelectionReturnsNoneWhenNoMethodWasChosen(): void
    {
        $selection = $this->subject(true)->resolveSelection(0, $this->basketViewModel($this->lightProduct, 1), 'DE', false);

        self::assertSame(0, $selection->getShippingMethodUid());
    }

    #[Test]
    public function resolveSelectionResolvesTheChosenMethodsRate(): void
    {
        $selection = $this->subject(true)->resolveSelection(1, $this->basketViewModel($this->lightProduct, 1), 'DE', false);

        self::assertSame(1, $selection->getShippingMethodUid());
        self::assertSame(500, $selection->getCost()->getCents());
    }

    #[Test]
    public function resolveSelectionZeroesTheCostWhenWaived(): void
    {
        $selection = $this->subject(true)->resolveSelection(1, $this->basketViewModel($this->lightProduct, 1), 'DE', true);

        self::assertSame(0, $selection->getCost()->getCents());
    }

    #[Test]
    public function resolveSelectionThrowsWhenTheChosenMethodNoLongerApplies(): void
    {
        $this->expectException(NoShippingMethodAvailableException::class);
        $this->expectExceptionCode(1783600000);

        // Method 1 (Standard DE) is capped at 1000g, the heavy basket weighs 1500g.
        $this->subject(true)->resolveSelection(1, $this->basketViewModel($this->heavyProduct, 1), 'DE', false);
    }

    private function subject(bool $enabled): ShippingCostService
    {
        return new ShippingCostService($this->get(ShippingMethodRepository::class), $this->fakeConfigurationManager($enabled));
    }

    private function fakeConfigurationManager(bool $enabled): ConfigurationManagerInterface
    {
        return new class ($enabled) implements ConfigurationManagerInterface {
            public function __construct(private readonly bool $enabled) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return ['shipping' => ['enabled' => $this->enabled]];
            }

            /**
             * @param array<string, mixed> $configuration
             */
            public function setConfiguration(array $configuration = []): void {}

            public function setRequest(ServerRequestInterface $request): void {}
        };
    }

    private function basketViewModel(Product $product, int $quantity): BasketViewModel
    {
        $unitPrice = $product->getPrice();
        $lineTotal = $unitPrice->multiply((float)$quantity);
        $item = new BasketViewItem($product, null, $quantity, $unitPrice, $unitPrice, 0.0, $lineTotal, $lineTotal, Money::fromCents(0));
        return new BasketViewModel([$item], $lineTotal, $lineTotal, Money::fromCents(0), 'EUR');
    }
}
