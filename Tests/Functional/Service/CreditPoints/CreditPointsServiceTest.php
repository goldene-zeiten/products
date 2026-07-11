<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\CreditPoints;

use GoldeneZeiten\Products\Configuration\CreditPointsConfiguration;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\CreditPointsEarningTier;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsService;
use GoldeneZeiten\Products\Service\CreditPoints\Exception\InsufficientCreditPointsException;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class CreditPointsServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private ProductRepository $productRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/credit_points.csv');
        $this->productRepository = $this->get(ProductRepository::class);
    }

    #[Test]
    public function balanceIsTheSumOfLedgerEntries(): void
    {
        $this->assertSame(70, $this->subject()->getBalance(1));
        $this->assertSame(50, $this->subject()->getBalance(2));
    }

    #[Test]
    public function balanceIsZeroForAFrontendUserWithNoLedgerEntries(): void
    {
        $this->assertSame(0, $this->subject()->getBalance(999));
    }

    #[Test]
    public function guestsAlwaysHaveAZeroBalanceWithoutQuerying(): void
    {
        $this->assertSame(0, $this->subject()->getBalance(0));
    }

    #[Test]
    public function manualAdjustmentRowsCountTowardTheBalance(): void
    {
        $this->assertSame(20, $this->subject()->getBalance(3));
    }

    #[Test]
    public function earnedPointsSumTheProductRateAcrossBasketLinesAndQuantities(): void
    {
        $this->assertSame(10 * 2 + 5 * 3, $this->subject()->calculateEarnedPoints($this->basketViewModel(), $this->configuration()));
    }

    #[Test]
    public function perProductModeIgnoresConfiguredTiers(): void
    {
        $configuration = $this->configuration(earningMode: 'perProduct', earningTiers: ['0.00:999']);

        $this->assertSame(10 * 2 + 5 * 3, $this->subject()->calculateEarnedPoints($this->basketViewModel(), $configuration));
    }

    #[Test]
    public function basketTieredModeAwardsTheHighestQualifyingTiersPoints(): void
    {
        $configuration = $this->configuration(earningMode: 'basketTiered', earningTiers: ['50.00:10', '100.00:25']);

        $this->assertSame(10, $this->subject()->calculateEarnedPoints($this->basketWithTotal('75.00'), $configuration));
        $this->assertSame(25, $this->subject()->calculateEarnedPoints($this->basketWithTotal('150.00'), $configuration));
    }

    #[Test]
    public function basketTieredModeAwardsNoPointsBelowTheLowestTier(): void
    {
        $configuration = $this->configuration(earningMode: 'basketTiered', earningTiers: ['50.00:10']);

        $this->assertSame(0, $this->subject()->calculateEarnedPoints($this->basketWithTotal('49.99'), $configuration));
    }

    #[Test]
    public function basketTieredModeAtExactlyTheThresholdQualifies(): void
    {
        $configuration = $this->configuration(earningMode: 'basketTiered', earningTiers: ['50.00:10']);

        $this->assertSame(10, $this->subject()->calculateEarnedPoints($this->basketWithTotal('50.00'), $configuration));
    }

    #[Test]
    public function autoPriceFactorModeUsesTheExplicitRateWhenPresent(): void
    {
        $configuration = $this->configuration(earningMode: 'autoPriceFactor', priceFactor: 1.0);

        $this->assertSame(10 * 2 + 5 * 3, $this->subject()->calculateEarnedPoints($this->basketViewModel(), $configuration));
    }

    #[Test]
    public function autoPriceFactorModeConvertsPriceToPointsForUnratedLines(): void
    {
        $configuration = $this->configuration(earningMode: 'autoPriceFactor', priceFactor: 2.0);
        $unratedProduct = new Product();
        $unratedProduct->setCreditPoints(0);
        $unitPrice = Money::fromDecimalString('10.00');
        $lineTotal = $unitPrice->multiply(3);
        $item = new BasketViewItem($unratedProduct, null, 3, $unitPrice, $unitPrice, 0.0, $lineTotal, $lineTotal, Money::fromCents(0));
        $basket = new BasketViewModel([$item], $lineTotal, $lineTotal, Money::fromCents(0), 'EUR');

        // lineTotalGross 30.00 * priceFactor 2.0 = 60 points
        $this->assertSame(60, $this->subject()->calculateEarnedPoints($basket, $configuration));
    }

    #[Test]
    public function redeemClampsToBalanceWhenMoreIsRequestedThanAvailable(): void
    {
        $redemption = $this->subject()->redeem(1, 1000, Money::fromDecimalString('1000.00'), $this->configuration());

        $this->assertSame(70, $redemption->getPoints());
        $this->assertSame(700, $redemption->getDiscountAmount()->getCents());
    }

    #[Test]
    public function redeemClampsToWhatTheBasketCanAbsorb(): void
    {
        $redemption = $this->subject()->redeem(1, 70, Money::fromDecimalString('3.00'), $this->configuration());

        $this->assertSame(30, $redemption->getPoints());
        $this->assertSame(300, $redemption->getDiscountAmount()->getCents());
    }

    #[Test]
    public function guestsCanNeverRedeemPoints(): void
    {
        $this->assertTrue($this->subject()->redeem(0, 100, Money::fromDecimalString('1000.00'), $this->configuration())->isEmpty());
    }

    #[Test]
    public function redeemIsANoOpWhenTheFeatureIsDisabled(): void
    {
        $configuration = $this->configuration(enabled: false);

        $this->assertTrue($this->subject()->redeem(1, 10, Money::fromDecimalString('100.00'), $configuration)->isEmpty());
    }

    #[Test]
    public function assertSpendableThrowsWhenRequestingMoreThanTheBalance(): void
    {
        $this->expectException(InsufficientCreditPointsException::class);
        $this->expectExceptionCode(1783430000);

        $this->subject()->assertSpendable(1, 71);
    }

    #[Test]
    public function assertSpendableAllowsRequestsWithinBalance(): void
    {
        $this->subject()->assertSpendable(1, 70);
        $this->addToAssertionCount(1);
    }

    private function subject(): CreditPointsService
    {
        return $this->get(CreditPointsService::class);
    }

    /**
     * @param string[] $earningTiers
     */
    private function configuration(bool $enabled = true, string $moneyPerPoint = '0.10', string $earningMode = 'perProduct', array $earningTiers = [], float $priceFactor = 0.0): CreditPointsConfiguration
    {
        return new CreditPointsConfiguration($enabled, Money::fromDecimalString($moneyPerPoint), $earningMode, $this->parseEarningTiers($earningTiers), $priceFactor);
    }

    /**
     * @param string[] $rawTiers
     * @return CreditPointsEarningTier[]
     */
    private function parseEarningTiers(array $rawTiers): array
    {
        $tiers = [];
        foreach ($rawTiers as $entry) {
            [$threshold, $points] = explode(':', $entry, 2);
            $tiers[] = new CreditPointsEarningTier(Money::fromDecimalString($threshold), (int)$points);
        }
        return $tiers;
    }

    private function basketViewModel(): BasketViewModel
    {
        $product1 = $this->productRepository->findByUid(1);
        $product2 = $this->productRepository->findByUid(2);
        $this->assertInstanceOf(Product::class, $product1);
        $this->assertInstanceOf(Product::class, $product2);

        $price = Money::fromDecimalString('10.00');
        $noTax = Money::fromCents(0);
        $items = [
            new BasketViewItem($product1, null, 2, $price, $price, 0.0, $price, $price, $noTax),
            new BasketViewItem($product2, null, 3, $price, $price, 0.0, $price, $price, $noTax),
        ];
        return new BasketViewModel($items, $price, $price, $noTax, 'EUR');
    }

    private function basketWithTotal(string $total): BasketViewModel
    {
        $money = Money::fromDecimalString($total);
        $noTax = Money::fromCents(0);
        return new BasketViewModel([], $money, $money, $noTax, 'EUR');
    }
}
