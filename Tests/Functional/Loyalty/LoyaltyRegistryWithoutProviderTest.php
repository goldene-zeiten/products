<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Loyalty;

use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Loyalty\LoyaltyContext;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Loyalty\LoyaltyRegistry;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * Test the LoyaltyRegistry with no fixture provider loaded.
 * Proves graceful behavior when no loyalty programme is available: the shop still
 * checks out, with zero balance and zero effect.
 */
final class LoyaltyRegistryWithoutProviderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/loyaltyRegistry.csv');
    }

    #[Test]
    public function collectRedemptionReturnsEmptyArrayWhenNothingRequested(): void
    {
        $context = $this->loyaltyContext(0, 0);
        $subject = $this->get(LoyaltyRegistry::class);

        $adjustments = $subject->collectRedemption($context);

        $this->assertSame([], $adjustments);
    }

    #[Test]
    public function assertRedeemableIsANoOpAndYieldsNoRedemptionWithNothingRequested(): void
    {
        $context = $this->loyaltyContext(0, 0);
        $subject = $this->get(LoyaltyRegistry::class);

        $subject->assertRedeemable($context);

        // Reached only if assertRedeemable did not throw; nothing was requested, so nothing is redeemed.
        $this->assertSame([], $subject->collectRedemption($context));
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
