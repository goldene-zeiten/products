<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Shipping;

use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\HandlingFeeRepository;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\Shipping\HandlingFeeService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

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
        $cost = $this->subject(false)->resolveCost($this->basketViewModel($this->lightProduct), 'DE');

        self::assertSame(0, $cost->getCents());
    }

    #[Test]
    public function resolveCostPicksTheApplicableFeeForALightBasket(): void
    {
        $cost = $this->subject(true)->resolveCost($this->basketViewModel($this->lightProduct), 'DE');

        self::assertSame(300, $cost->getCents());
    }

    #[Test]
    public function resolveCostPicksTheApplicableFeeForAHeavyBasket(): void
    {
        $cost = $this->subject(true)->resolveCost($this->basketViewModel($this->heavyProduct), 'DE');

        self::assertSame(900, $cost->getCents());
    }

    #[Test]
    public function resolveCostIsZeroWhenNothingApplies(): void
    {
        $cost = $this->subject(true)->resolveCost($this->basketViewModel($this->lightProduct), 'FR');

        self::assertSame(0, $cost->getCents());
    }

    private function subject(bool $enabled): HandlingFeeService
    {
        return new HandlingFeeService($this->get(HandlingFeeRepository::class), $this->fakeConfigurationManager($enabled));
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
                return ['handling' => ['enabled' => $this->enabled]];
            }

            /**
             * @param array<string, mixed> $configuration
             */
            public function setConfiguration(array $configuration = []): void {}

            public function setRequest(ServerRequestInterface $request): void {}
        };
    }

    private function basketViewModel(Product $product): BasketViewModel
    {
        $unitPrice = $product->getPrice();
        $item = new BasketViewItem($product, null, 1, $unitPrice, $unitPrice, 0.0, $unitPrice, $unitPrice, Money::fromCents(0));
        return new BasketViewModel([$item], $unitPrice, $unitPrice, Money::fromCents(0), 'EUR');
    }
}
