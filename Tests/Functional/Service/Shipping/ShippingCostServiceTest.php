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

    private Product $lightProduct;
    private Product $heavyProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/shipping_methods.csv');
        $productRepository = $this->get(ProductRepository::class);
        $lightProduct = $productRepository->findByUid(1);
        $heavyProduct = $productRepository->findByUid(2);
        $this->assertInstanceOf(Product::class, $lightProduct);
        $this->assertInstanceOf(Product::class, $heavyProduct);
        $this->lightProduct = $lightProduct;
        $this->heavyProduct = $heavyProduct;
    }

    #[Test]
    public function countrySpecificMethodsTakePrecedenceOverTheFallback(): void
    {
        $methods = $this->get(ShippingCostService::class)->resolveAvailable($this->configuration(true), $this->basketViewModel($this->lightProduct, 1), 'DE');

        $titles = array_map(static fn($method): string => $method->getTitle(), $methods);
        $this->assertContains('Standard DE', $titles);
        $this->assertNotContains('Fallback', $titles);
    }

    #[Test]
    public function theFallbackIsUsedWhenNoCountrySpecificMethodExists(): void
    {
        $methods = $this->get(ShippingCostService::class)->resolveAvailable($this->configuration(true), $this->basketViewModel($this->lightProduct, 1), 'AT');

        $this->assertCount(1, $methods);
        $this->assertSame('Fallback', $methods[0]->getTitle());
    }

    #[Test]
    public function aBasketAboveTheWeightTierGetsOnlyTheHeavyMethod(): void
    {
        $methods = $this->get(ShippingCostService::class)->resolveAvailable($this->configuration(true), $this->basketViewModel($this->heavyProduct, 1), 'DE');

        $titles = array_map(static fn($method): string => $method->getTitle(), $methods);
        $this->assertContains('Heavy DE', $titles);
        $this->assertNotContains('Standard DE', $titles);
    }

    #[Test]
    public function aBasketBelowTheMinimumOrderValueExcludesThatMethod(): void
    {
        $methods = $this->get(ShippingCostService::class)->resolveAvailable($this->configuration(true), $this->basketViewModel($this->lightProduct, 1), 'DE');

        $titles = array_map(static fn($method): string => $method->getTitle(), $methods);
        $this->assertNotContains('Big Orders Only DE', $titles);
    }

    #[Test]
    public function nothingIsAvailableWhenTheFeatureIsDisabled(): void
    {
        $methods = $this->get(ShippingCostService::class)->resolveAvailable($this->configuration(false), $this->basketViewModel($this->lightProduct, 1), 'DE');

        $this->assertSame([], $methods);
    }

    #[Test]
    public function resolveSelectionReturnsNoneWhenTheFeatureIsDisabled(): void
    {
        $criteria = new ShippingSelectionCriteria(1, $this->basketViewModel($this->lightProduct, 1), 'DE', false);

        $selection = $this->get(ShippingCostService::class)->resolveSelection($this->configuration(false), $criteria);

        $this->assertSame(0, $selection->getShippingMethodUid());
        $this->assertSame(0, $selection->getCost()->getCents());
    }

    #[Test]
    public function resolveSelectionReturnsNoneWhenNoMethodWasChosen(): void
    {
        $criteria = new ShippingSelectionCriteria(0, $this->basketViewModel($this->lightProduct, 1), 'DE', false);

        $selection = $this->get(ShippingCostService::class)->resolveSelection($this->configuration(true), $criteria);

        $this->assertSame(0, $selection->getShippingMethodUid());
    }

    #[Test]
    public function resolveSelectionResolvesTheChosenMethodsRate(): void
    {
        $criteria = new ShippingSelectionCriteria(1, $this->basketViewModel($this->lightProduct, 1), 'DE', false);

        $selection = $this->get(ShippingCostService::class)->resolveSelection($this->configuration(true), $criteria);

        $this->assertSame(1, $selection->getShippingMethodUid());
        $this->assertSame(500, $selection->getCost()->getCents());
    }

    #[Test]
    public function resolveSelectionZeroesTheCostWhenWaived(): void
    {
        $criteria = new ShippingSelectionCriteria(1, $this->basketViewModel($this->lightProduct, 1), 'DE', true);

        $selection = $this->get(ShippingCostService::class)->resolveSelection($this->configuration(true), $criteria);

        $this->assertSame(0, $selection->getCost()->getCents());
    }

    #[Test]
    public function resolveSelectionThrowsWhenTheChosenMethodNoLongerApplies(): void
    {
        $this->expectException(NoShippingMethodAvailableException::class);
        $this->expectExceptionCode(1783600000);

        // Method 1 (Standard DE) is capped at 1000g, the heavy basket weighs 1500g.
        $criteria = new ShippingSelectionCriteria(1, $this->basketViewModel($this->heavyProduct, 1), 'DE', false);
        $this->get(ShippingCostService::class)->resolveSelection($this->configuration(true), $criteria);
    }

    #[Test]
    public function resolveSelectionAddsTheBulkySurchargeOncePerBulkyUnit(): void
    {
        $this->lightProduct->setBulky(true);

        // Quantity 2 keeps the basket at exactly method 1's 1000g cap (500g each).
        $criteria = new ShippingSelectionCriteria(1, $this->basketViewModel($this->lightProduct, 2), 'DE', false);
        $selection = $this->get(ShippingCostService::class)->resolveSelection($this->configuration(true, '2.50'), $criteria);

        $this->assertSame(500 + 500, $selection->getCost()->getCents());
    }

    #[Test]
    public function resolveSelectionBulkySurchargeStillAppliesWhenShippingIsWaived(): void
    {
        $this->lightProduct->setBulky(true);

        $criteria = new ShippingSelectionCriteria(1, $this->basketViewModel($this->lightProduct, 1), 'DE', true);
        $selection = $this->get(ShippingCostService::class)->resolveSelection($this->configuration(true, '2.50'), $criteria);

        $this->assertSame(250, $selection->getCost()->getCents());
    }

    #[Test]
    public function resolveSelectionHasNoSurchargeForNonBulkyItems(): void
    {
        $criteria = new ShippingSelectionCriteria(1, $this->basketViewModel($this->lightProduct, 2), 'DE', false);
        $selection = $this->get(ShippingCostService::class)->resolveSelection($this->configuration(true, '2.50'), $criteria);

        $this->assertSame(500, $selection->getCost()->getCents());
    }

    #[Test]
    public function resolveSelectionResolvesTheStandardTaxClassRateByDefault(): void
    {
        // Method 1 has no tax override; the fixture's "standard" tax class carries 19% for DE.
        $criteria = new ShippingSelectionCriteria(1, $this->basketViewModel($this->lightProduct, 1), 'DE', false);
        $selection = $this->get(ShippingCostService::class)->resolveSelection($this->configuration(true), $criteria);

        $this->assertSame(0.19, $selection->getTaxRate());
    }

    #[Test]
    public function resolveSelectionUsesTheMethodsTaxRateOverrideWhenEnabled(): void
    {
        // Method 2 (Heavy DE) has an enabled override of 7% instead of the standard 19%.
        $criteria = new ShippingSelectionCriteria(2, $this->basketViewModel($this->heavyProduct, 1), 'DE', false);
        $selection = $this->get(ShippingCostService::class)->resolveSelection($this->configuration(true), $criteria);

        $this->assertSame(0.07, $selection->getTaxRate());
    }

    #[Test]
    public function resolveSelectionAppliesTheShoppersDiscountToTheMethodsRateButNotTheSurcharge(): void
    {
        $this->lightProduct->setBulky(true);
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/frontend_user_discounts.csv');

        // user 3 has a personal 15% discount (see frontend_user_discounts.csv): 5.00 * 0.85 = 4.25, plus the untouched 2.50 surcharge.
        $criteria = new ShippingSelectionCriteria(1, $this->basketViewModel($this->lightProduct, 1), 'DE', false);
        $selection = $this->get(ShippingCostService::class)->resolveSelection($this->configuration(true, '2.50'), $criteria, $this->requestFor(3));

        $this->assertSame(425 + 250, $selection->getCost()->getCents());
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
