<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Loyalty;

use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Loyalty\LoyaltyContext;
use GoldeneZeiten\Products\Core\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Loyalty\LoyaltyRegistry;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\LoyaltyFixture\FixtureLoyaltyException;
use GoldeneZeiten\Products\LoyaltyFixture\FixtureLoyaltyProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * Test the LoyaltyRegistry with the fixture loyalty provider to prove the contract
 * and the tagged_iterator wiring work correctly.
 */
final class LoyaltyRegistryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
        'goldene-zeiten/products-loyalty-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/loyaltyRegistry.csv');
        FixtureLoyaltyProvider::reset();
    }

    protected function tearDown(): void
    {
        FixtureLoyaltyProvider::reset();
        parent::tearDown();
    }

    #[Test]
    public function fixtureProviderIsCollectedViaTag(): void
    {
        FixtureLoyaltyProvider::$balances[7] = 100;

        $context = $this->loyaltyContext(7, 0);
        $subject = $this->get(LoyaltyRegistry::class);

        $this->assertSame(100, $subject->getBalance($context));
    }

    #[Test]
    public function collectRedemptionReturnsAdjustmentForPointsRequested(): void
    {
        FixtureLoyaltyProvider::$balances[7] = 100;

        $context = $this->loyaltyContext(7, 30);
        $subject = $this->get(LoyaltyRegistry::class);

        $adjustments = $subject->collectRedemption($context);

        $this->assertCount(1, $adjustments);
        $this->assertSame(AdjustmentType::LOYALTY, $adjustments[0]->getType());
        $this->assertSame('fixture-loyalty', $adjustments[0]->getProviderIdentifier());
        $this->assertSame(-30, $adjustments[0]->getAmount()->getCents());
    }

    #[Test]
    public function collectRedemptionReturnsEmptyArrayWhenNothingRequested(): void
    {
        FixtureLoyaltyProvider::$balances[7] = 100;

        $context = $this->loyaltyContext(7, 0);
        $subject = $this->get(LoyaltyRegistry::class);

        $adjustments = $subject->collectRedemption($context);

        $this->assertSame([], $adjustments);
    }

    #[Test]
    public function assertRedeemableThrowsWhenRequestedExceedsBalance(): void
    {
        FixtureLoyaltyProvider::$balances[7] = 100;

        $context = $this->loyaltyContext(7, 200);
        $subject = $this->get(LoyaltyRegistry::class);

        $this->expectException(FixtureLoyaltyException::class);
        $this->expectExceptionCode(1784073640);

        $subject->assertRedeemable($context);
    }

    #[Test]
    public function roundTripRedeemAndAward(): void
    {
        FixtureLoyaltyProvider::$balances[7] = 100;

        $order = new Order();
        $context = $this->loyaltyContext(7, 30);
        $subject = $this->get(LoyaltyRegistry::class);

        // Redeem 30 points
        $subject->applyRedemption($order, $context);
        $this->assertSame(70, FixtureLoyaltyProvider::$balances[7]);

        // Award 5 points
        $subject->award($order, $context);
        $this->assertSame(75, FixtureLoyaltyProvider::$balances[7]);
    }

    private function loyaltyContext(int $frontendUserUid, int $requestedSpendPoints): LoyaltyContext
    {
        $request = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $product = $this->get(ProductRepository::class)->findByUid(1);
        $this->assertNotNull($product);

        $unitPriceNet = Money::fromDecimalString('84.03');
        $unitPriceGross = Money::fromDecimalString('100.00');
        $item = new BasketViewItem(
            $product,
            null,
            1,
            $unitPriceNet,
            $unitPriceGross,
            0.19,
            $unitPriceNet,
            $unitPriceGross,
            $unitPriceGross->subtract($unitPriceNet)
        );
        $basket = new BasketViewModel(
            [$item],
            $unitPriceNet,
            $unitPriceGross,
            $unitPriceGross->subtract($unitPriceNet),
            'EUR'
        );

        $remainingGoodsTotal = Money::fromDecimalString('100.00');

        return new LoyaltyContext(
            $request,
            $basket,
            $remainingGoodsTotal,
            $frontendUserUid,
            $requestedSpendPoints
        );
    }
}
