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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class HandlingFeeServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/HandlingFeeServiceTest/handling_fees.csv');
    }

    #[Test]
    #[DataProvider('resolveCostProvider')]
    public function resolveCostPicksTheApplicableFee(bool $enabled, int $productUid, string $country, int $expectedCents): void
    {
        $subject = $this->get(HandlingFeeService::class);

        $cost = $subject->resolveCost($this->configuration($enabled), $this->basketViewModel($this->product($productUid)), $country);

        $this->assertSame($expectedCents, $cost->getCents());
    }

    public static function resolveCostProvider(): \Generator
    {
        yield 'zero when disabled' => ['enabled' => false, 'productUid' => 1, 'country' => 'DE', 'expectedCents' => 0];
        yield 'applicable fee for a light basket' => ['enabled' => true, 'productUid' => 1, 'country' => 'DE', 'expectedCents' => 300];
        yield 'applicable fee for a heavy basket' => ['enabled' => true, 'productUid' => 2, 'country' => 'DE', 'expectedCents' => 900];
        yield 'zero when nothing applies' => ['enabled' => true, 'productUid' => 1, 'country' => 'FR', 'expectedCents' => 0];
    }

    private function product(int $uid): Product
    {
        $product = $this->get(ProductRepository::class)->findByUid($uid);
        $this->assertInstanceOf(Product::class, $product);
        return $product;
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
