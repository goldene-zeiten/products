<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Shipping;

use GoldeneZeiten\Products\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\ShippingSelectionCriteria;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\Shipping\Exception\NoShippingMethodAvailableException;
use GoldeneZeiten\Products\Service\Shipping\ShippingCostService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class ShippingCostServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ShippingCostServiceTest/shipping_methods.csv');
    }

    /**
     * @param string[] $expectedPresentTitles
     * @param string[] $expectedAbsentTitles
     */
    #[Test]
    #[DataProvider('resolveAvailableProvider')]
    public function resolveAvailableReturnsTheApplicableMethods(string $productKind, string $country, array $expectedPresentTitles, array $expectedAbsentTitles, ?int $expectedCount): void
    {
        $subject = $this->get(ShippingCostService::class);
        $product = $productKind === 'heavy' ? $this->heavyProduct() : $this->lightProduct();

        $methods = $subject->resolveAvailable($this->configuration(true), $this->basketViewModel($product, 1), $country);

        $titles = array_map(static fn($method): string => $method->getTitle(), $methods);
        foreach ($expectedPresentTitles as $expectedPresentTitle) {
            $this->assertContains($expectedPresentTitle, $titles);
        }
        foreach ($expectedAbsentTitles as $expectedAbsentTitle) {
            $this->assertNotContains($expectedAbsentTitle, $titles);
        }
        if ($expectedCount !== null) {
            $this->assertCount($expectedCount, $methods);
        }
    }

    public static function resolveAvailableProvider(): \Generator
    {
        yield 'country specific methods take precedence over the fallback' => [
            'productKind' => 'light', 'country' => 'DE', 'expectedPresentTitles' => ['Standard DE'], 'expectedAbsentTitles' => ['Fallback'], 'expectedCount' => null,
        ];
        yield 'the fallback is used when no country specific method exists' => [
            'productKind' => 'light', 'country' => 'AT', 'expectedPresentTitles' => ['Fallback'], 'expectedAbsentTitles' => [], 'expectedCount' => 1,
        ];
        yield 'a basket above the weight tier gets only the heavy method' => [
            'productKind' => 'heavy', 'country' => 'DE', 'expectedPresentTitles' => ['Heavy DE'], 'expectedAbsentTitles' => ['Standard DE'], 'expectedCount' => null,
        ];
        yield 'a basket below the minimum order value excludes that method' => [
            'productKind' => 'light', 'country' => 'DE', 'expectedPresentTitles' => [], 'expectedAbsentTitles' => ['Big Orders Only DE'], 'expectedCount' => null,
        ];
    }

    #[Test]
    public function nothingIsAvailableWhenTheFeatureIsDisabled(): void
    {
        $subject = $this->get(ShippingCostService::class);

        $methods = $subject->resolveAvailable($this->configuration(false), $this->basketViewModel($this->lightProduct(), 1), 'DE');

        $this->assertSame([], $methods);
    }

    #[Test]
    #[DataProvider('resolveSelectionNoneProvider')]
    public function resolveSelectionReturnsNoneInVariousScenarios(bool $enabled, int $chosenMethodUid, int $expectedShippingMethodUid, ?int $expectedCostCents): void
    {
        $subject = $this->get(ShippingCostService::class);
        $criteria = new ShippingSelectionCriteria($chosenMethodUid, $this->basketViewModel($this->lightProduct(), 1), 'DE', false);

        $selection = $subject->resolveSelection($this->configuration($enabled), $criteria);

        $this->assertSame($expectedShippingMethodUid, $selection->getShippingMethodUid());
        if ($expectedCostCents !== null) {
            $this->assertSame($expectedCostCents, $selection->getCost()->getCents());
        }
    }

    public static function resolveSelectionNoneProvider(): \Generator
    {
        yield 'returns none when the feature is disabled' => ['enabled' => false, 'chosenMethodUid' => 1, 'expectedShippingMethodUid' => 0, 'expectedCostCents' => 0];
        yield 'returns none when no method was chosen' => ['enabled' => true, 'chosenMethodUid' => 0, 'expectedShippingMethodUid' => 0, 'expectedCostCents' => null];
    }

    #[Test]
    public function resolveSelectionResolvesTheChosenMethodsRate(): void
    {
        $subject = $this->get(ShippingCostService::class);
        $criteria = new ShippingSelectionCriteria(1, $this->basketViewModel($this->lightProduct(), 1), 'DE', false);

        $selection = $subject->resolveSelection($this->configuration(true), $criteria);

        $this->assertSame(1, $selection->getShippingMethodUid());
        $this->assertSame(500, $selection->getCost()->getCents());
    }

    #[Test]
    public function resolveSelectionZeroesTheCostWhenWaived(): void
    {
        $subject = $this->get(ShippingCostService::class);
        $criteria = new ShippingSelectionCriteria(1, $this->basketViewModel($this->lightProduct(), 1), 'DE', true);

        $selection = $subject->resolveSelection($this->configuration(true), $criteria);

        $this->assertSame(0, $selection->getCost()->getCents());
    }

    #[Test]
    public function resolveSelectionThrowsWhenTheChosenMethodNoLongerApplies(): void
    {
        $this->expectException(NoShippingMethodAvailableException::class);
        $this->expectExceptionCode(1783600000);

        $subject = $this->get(ShippingCostService::class);
        // Method 1 (Standard DE) is capped at 1000g, the heavy basket weighs 1500g.
        $criteria = new ShippingSelectionCriteria(1, $this->basketViewModel($this->heavyProduct(), 1), 'DE', false);
        $subject->resolveSelection($this->configuration(true), $criteria);
    }

    #[Test]
    #[DataProvider('bulkySurchargeProvider')]
    public function resolveSelectionAppliesTheBulkySurchargeCorrectly(bool $bulky, int $quantity, bool $waived, int $expectedCostCents): void
    {
        $subject = $this->get(ShippingCostService::class);
        $lightProduct = $this->lightProduct();
        $lightProduct->setBulky($bulky);

        // Quantity 2 keeps the basket at exactly method 1's 1000g cap (500g each).
        $criteria = new ShippingSelectionCriteria(1, $this->basketViewModel($lightProduct, $quantity), 'DE', $waived);
        $selection = $subject->resolveSelection($this->configuration(true, '2.50'), $criteria);

        $this->assertSame($expectedCostCents, $selection->getCost()->getCents());
    }

    public static function bulkySurchargeProvider(): \Generator
    {
        yield 'adds the surcharge once per bulky unit' => ['bulky' => true, 'quantity' => 2, 'waived' => false, 'expectedCostCents' => 500 + 500];
        yield 'surcharge still applies when shipping is waived' => ['bulky' => true, 'quantity' => 1, 'waived' => true, 'expectedCostCents' => 250];
        yield 'no surcharge for non-bulky items' => ['bulky' => false, 'quantity' => 2, 'waived' => false, 'expectedCostCents' => 500];
    }

    #[Test]
    #[DataProvider('resolveSelectionTaxRateProvider')]
    public function resolveSelectionResolvesTheApplicableTaxRate(int $methodUid, string $productKind, float $expectedTaxRate): void
    {
        $subject = $this->get(ShippingCostService::class);
        $product = $productKind === 'heavy' ? $this->heavyProduct() : $this->lightProduct();
        $criteria = new ShippingSelectionCriteria($methodUid, $this->basketViewModel($product, 1), 'DE', false);

        $selection = $subject->resolveSelection($this->configuration(true), $criteria);

        $this->assertSame($expectedTaxRate, $selection->getTaxRate());
    }

    public static function resolveSelectionTaxRateProvider(): \Generator
    {
        // Method 1 has no tax override; the fixture's "standard" tax class carries 19% for DE.
        yield 'resolves the standard tax class rate by default' => ['methodUid' => 1, 'productKind' => 'light', 'expectedTaxRate' => 0.19];
        // Method 2 (Heavy DE) has an enabled override of 7% instead of the standard 19%.
        yield 'uses the method\'s tax rate override when enabled' => ['methodUid' => 2, 'productKind' => 'heavy', 'expectedTaxRate' => 0.07];
    }

    #[Test]
    public function resolveSelectionAppliesTheShoppersDiscountToTheMethodsRateButNotTheSurcharge(): void
    {
        $subject = $this->get(ShippingCostService::class);
        $lightProduct = $this->lightProduct();
        $lightProduct->setBulky(true);
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/frontend_user_discounts.csv');

        // user 3 has a personal 15% discount (see frontend_user_discounts.csv): 5.00 * 0.85 = 4.25, plus the untouched 2.50 surcharge.
        $criteria = new ShippingSelectionCriteria(1, $this->basketViewModel($lightProduct, 1), 'DE', false);
        $selection = $subject->resolveSelection($this->configuration(true, '2.50'), $criteria, $this->requestFor(3));

        $this->assertSame(425 + 250, $selection->getCost()->getCents());
    }

    private function lightProduct(): Product
    {
        $product = $this->get(ProductRepository::class)->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);
        return $product;
    }

    private function heavyProduct(): Product
    {
        $product = $this->get(ProductRepository::class)->findByUid(2);
        $this->assertInstanceOf(Product::class, $product);
        return $product;
    }

    private function configuration(bool $shippingEnabled, string $bulkySurcharge = '0.00'): ProductsConfiguration
    {
        return new ProductsConfiguration('DE', 'gross', 'EUR', $shippingEnabled, Money::fromDecimalString($bulkySurcharge), false, 'none');
    }

    private function requestFor(int $frontendUserUid): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        if ($frontendUserUid > 0) {
            $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('fe_users');
            $row = $queryBuilder->select('*')
                ->from('fe_users')
                ->where($queryBuilder->expr()->eq('uid', $frontendUserUid))
                ->executeQuery()
                ->fetchAssociative();
            $frontendUser->user = $row !== false ? $row : ['uid' => $frontendUserUid];
        }
        return (new ServerRequest('http://localhost/'))->withAttribute('frontend.user', $frontendUser);
    }

    private function basketViewModel(Product $product, int $quantity): BasketViewModel
    {
        $unitPrice = $product->getPrice();
        $lineTotal = $unitPrice->multiply((float)$quantity);
        $item = new BasketViewItem($product, null, $quantity, $unitPrice, $unitPrice, 0.0, $lineTotal, $lineTotal, Money::fromCents(0));
        return new BasketViewModel([$item], $lineTotal, $lineTotal, Money::fromCents(0), 'EUR');
    }
}
