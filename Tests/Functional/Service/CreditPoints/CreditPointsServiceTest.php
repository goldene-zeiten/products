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

    private function subject(bool $enabled = true, string $moneyPerPoint = '0.10'): CreditPointsService
    {
        return new CreditPointsService(
            $this->get(ConnectionPool::class),
            $this->fakeConfigurationManager($enabled, $moneyPerPoint)
        );
    }

    private function fakeConfigurationManager(bool $enabled, string $moneyPerPoint): ConfigurationManagerInterface
    {
        return new class ($enabled, $moneyPerPoint) implements ConfigurationManagerInterface {
            public function __construct(
                private readonly bool $enabled,
                private readonly string $moneyPerPoint
            ) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return ['creditPoints' => ['enabled' => $this->enabled, 'moneyPerPoint' => $this->moneyPerPoint]];
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
}
