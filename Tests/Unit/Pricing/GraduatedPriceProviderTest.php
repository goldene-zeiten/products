<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Pricing;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\PriceTier;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Pricing\GraduatedPriceProvider;
use GoldeneZeiten\Products\Pricing\ProductPriceProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class GraduatedPriceProviderTest extends UnitTestCase
{
    private GraduatedPriceProvider $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new GraduatedPriceProvider(new ProductPriceProvider());
    }

    #[Test]
    public function fallsBackToBasePriceWithoutTiers(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('19.99'));

        $this->assertSame(1999, $this->subject->getUnitPrice($product, null, 5)->getCents());
    }

    #[Test]
    public function belowFirstTierUsesBasePrice(): void
    {
        $product = $this->productWithTiers();

        $this->assertSame(1999, $this->subject->getUnitPrice($product, null, 1)->getCents());
    }

    #[Test]
    public function exactTierBoundaryUsesThatTier(): void
    {
        $product = $this->productWithTiers();

        $this->assertSame(1500, $this->subject->getUnitPrice($product, null, 10)->getCents());
    }

    #[Test]
    public function aboveLastTierUsesHighestTier(): void
    {
        $product = $this->productWithTiers();

        $this->assertSame(1200, $this->subject->getUnitPrice($product, null, 1000)->getCents());
    }

    #[Test]
    public function articleTiersTakePrecedenceOverProductTiers(): void
    {
        $product = $this->productWithTiers();
        $article = new Article();
        $article->setProduct($product);
        /** @var ObjectStorage<PriceTier> $articleTiers */
        $articleTiers = new ObjectStorage();
        $articleTiers->attach($this->tier(10, '9.00'));
        $article->setPriceTiers($articleTiers);

        $this->assertSame(900, $this->subject->getUnitPrice($product, $article, 10)->getCents());
    }

    #[Test]
    public function productTiersApplyWhenArticleHasNone(): void
    {
        $product = $this->productWithTiers();
        $article = new Article();
        $article->setProduct($product);

        $this->assertSame(1500, $this->subject->getUnitPrice($product, $article, 10)->getCents());
    }

    private function productWithTiers(): Product
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('19.99'));
        /** @var ObjectStorage<PriceTier> $tiers */
        $tiers = new ObjectStorage();
        $tiers->attach($this->tier(10, '15.00'));
        $tiers->attach($this->tier(50, '12.00'));
        $product->setPriceTiers($tiers);
        return $product;
    }

    private function tier(int $minQuantity, string $price): PriceTier
    {
        $tier = new PriceTier();
        $tier->setMinQuantity($minQuantity);
        $tier->setPrice(Money::fromDecimalString($price));
        return $tier;
    }
}
