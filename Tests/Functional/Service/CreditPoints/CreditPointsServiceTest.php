<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\CreditPoints;

use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsService;
use GoldeneZeiten\Products\Service\CreditPoints\Exception\InsufficientCreditPointsException;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

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
        self::assertSame(70, $this->subject()->getBalance(1));
        self::assertSame(50, $this->subject()->getBalance(2));
    }

    #[Test]
    public function balanceIsZeroForAFrontendUserWithNoLedgerEntries(): void
    {
        self::assertSame(0, $this->subject()->getBalance(999));
    }

    #[Test]
    public function guestsAlwaysHaveAZeroBalanceWithoutQuerying(): void
    {
        self::assertSame(0, $this->subject()->getBalance(0));
    }

    #[Test]
    public function manualAdjustmentRowsCountTowardTheBalance(): void
    {
        self::assertSame(20, $this->subject()->getBalance(3));
    }

    #[Test]
    public function earnedPointsSumTheProductRateAcrossBasketLinesAndQuantities(): void
    {
        self::assertSame(10 * 2 + 5 * 3, $this->subject()->calculateEarnedPoints($this->basketViewModel()));
    }

    #[Test]
    public function perProductModeIgnoresConfiguredTiers(): void
    {
        $subject = $this->subject(earningMode: 'perProduct', earningTiers: ['0.00:999']);

        self::assertSame(10 * 2 + 5 * 3, $subject->calculateEarnedPoints($this->basketViewModel()));
    }

    #[Test]
    public function basketTieredModeAwardsTheHighestQualifyingTiersPoints(): void
    {
        $subject = $this->subject(earningMode: 'basketTiered', earningTiers: ['50.00:10', '100.00:25']);

        self::assertSame(10, $subject->calculateEarnedPoints($this->basketWithTotal('75.00')));
        self::assertSame(25, $subject->calculateEarnedPoints($this->basketWithTotal('150.00')));
    }

    #[Test]
    public function basketTieredModeAwardsNoPointsBelowTheLowestTier(): void
    {
        $subject = $this->subject(earningMode: 'basketTiered', earningTiers: ['50.00:10']);

        self::assertSame(0, $subject->calculateEarnedPoints($this->basketWithTotal('49.99')));
    }

    #[Test]
    public function basketTieredModeAtExactlyTheThresholdQualifies(): void
    {
        $subject = $this->subject(earningMode: 'basketTiered', earningTiers: ['50.00:10']);

        self::assertSame(10, $subject->calculateEarnedPoints($this->basketWithTotal('50.00')));
    }

    #[Test]
    public function basketTieredModeIgnoresMalformedTierEntries(): void
    {
        $subject = $this->subject(earningMode: 'basketTiered', earningTiers: ['not-a-tier', '50.00:10']);

        self::assertSame(10, $subject->calculateEarnedPoints($this->basketWithTotal('75.00')));
    }

    #[Test]
    public function autoPriceFactorModeUsesTheExplicitRateWhenPresent(): void
    {
        $subject = $this->subject(earningMode: 'autoPriceFactor', priceFactor: 1.0);

        self::assertSame(10 * 2 + 5 * 3, $subject->calculateEarnedPoints($this->basketViewModel()));
    }

    #[Test]
    public function autoPriceFactorModeConvertsPriceToPointsForUnratedLines(): void
    {
        $subject = $this->subject(earningMode: 'autoPriceFactor', priceFactor: 2.0);
        $unratedProduct = new Product();
        $unratedProduct->setCreditPoints(0);
        $unitPrice = Money::fromDecimalString('10.00');
        $lineTotal = $unitPrice->multiply(3);
        $item = new BasketViewItem($unratedProduct, null, 3, $unitPrice, $unitPrice, 0.0, $lineTotal, $lineTotal, Money::fromCents(0));
        $basket = new BasketViewModel([$item], $lineTotal, $lineTotal, Money::fromCents(0), 'EUR');

        // lineTotalGross 30.00 * priceFactor 2.0 = 60 points
        self::assertSame(60, $subject->calculateEarnedPoints($basket));
    }

    #[Test]
    public function redeemClampsToBalanceWhenMoreIsRequestedThanAvailable(): void
    {
        $redemption = $this->subject()->redeem(1, 1000, Money::fromDecimalString('1000.00'));

        self::assertSame(70, $redemption->getPoints());
        self::assertSame(700, $redemption->getDiscountAmount()->getCents());
    }

    #[Test]
    public function redeemClampsToWhatTheBasketCanAbsorb(): void
    {
        $redemption = $this->subject()->redeem(1, 70, Money::fromDecimalString('3.00'));

        self::assertSame(30, $redemption->getPoints());
        self::assertSame(300, $redemption->getDiscountAmount()->getCents());
    }

    #[Test]
    public function guestsCanNeverRedeemPoints(): void
    {
        self::assertTrue($this->subject()->redeem(0, 100, Money::fromDecimalString('1000.00'))->isEmpty());
    }

    #[Test]
    public function redeemIsANoOpWhenTheFeatureIsDisabled(): void
    {
        self::assertTrue($this->subject(enabled: false)->redeem(1, 10, Money::fromDecimalString('100.00'))->isEmpty());
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

    /**
     * @param string[] $earningTiers
     */
    private function subject(bool $enabled = true, string $moneyPerPoint = '0.10', string $earningMode = 'perProduct', array $earningTiers = [], float $priceFactor = 0.0): CreditPointsService
    {
        return new CreditPointsService(
            $this->get(ConnectionPool::class),
            $this->fakeConfigurationManager($enabled, $moneyPerPoint, $earningMode, $earningTiers, $priceFactor)
        );
    }

    /**
     * @param string[] $earningTiers
     */
    private function fakeConfigurationManager(bool $enabled, string $moneyPerPoint, string $earningMode, array $earningTiers, float $priceFactor = 0.0): ConfigurationManagerInterface
    {
        return new class ($enabled, $moneyPerPoint, $earningMode, $earningTiers, $priceFactor) implements ConfigurationManagerInterface {
            /**
             * @param string[] $earningTiers
             */
            public function __construct(
                private readonly bool $enabled,
                private readonly string $moneyPerPoint,
                private readonly string $earningMode,
                private readonly array $earningTiers,
                private readonly float $priceFactor
            ) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return ['creditPoints' => [
                    'enabled' => $this->enabled,
                    'moneyPerPoint' => $this->moneyPerPoint,
                    'earningMode' => $this->earningMode,
                    'earningTiers' => $this->earningTiers,
                    'priceFactor' => $this->priceFactor,
                ]];
            }

            /**
             * @param array<string, mixed> $configuration
             */
            public function setConfiguration(array $configuration = []): void {}

            public function setRequest(ServerRequestInterface $request): void {}
        };
    }

    private function basketViewModel(): BasketViewModel
    {
        $product1 = $this->productRepository->findByUid(1);
        $product2 = $this->productRepository->findByUid(2);
        self::assertInstanceOf(Product::class, $product1);
        self::assertInstanceOf(Product::class, $product2);

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
