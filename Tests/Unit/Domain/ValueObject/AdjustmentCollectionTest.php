<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Domain\ValueObject;

use GoldeneZeiten\Products\Core\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
use GoldeneZeiten\Products\Core\Domain\ValueObject\CheckoutAdjustment;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AdjustmentCollectionTest extends UnitTestCase
{
    #[Test]
    public function emptyCollectionReturnsZeroForAllTotals(): void
    {
        $collection = new AdjustmentCollection();

        $this->assertSame(0, $collection->getTotal()->getCents());
        $this->assertSame(0, $collection->getNetTotal()->getCents());
        $this->assertSame(0, $collection->getTaxTotal()->getCents());
        $this->assertSame(0, $collection->getDiscountTotal()->getCents());
    }

    #[Test]
    public function getTotalSumsSignedAmounts(): void
    {
        $shipping = new CheckoutAdjustment(
            AdjustmentType::SHIPPING,
            'core.shipping',
            'Shipping',
            Money::fromCents(500)
        );
        $discount = new CheckoutAdjustment(
            AdjustmentType::DISCOUNT,
            'core.voucher',
            'Discount',
            Money::fromCents(-200)
        );
        $collection = new AdjustmentCollection($shipping, $discount);

        $this->assertSame(300, $collection->getTotal()->getCents());
    }

    #[Test]
    #[DataProvider('getTotalByTypeProvider')]
    public function getTotalByTypeIsolatesOneType(AdjustmentType $type, int $expectedCents): void
    {
        $shipping = new CheckoutAdjustment(
            AdjustmentType::SHIPPING,
            'core.shipping',
            'Shipping',
            Money::fromCents(500),
            0.19
        );
        $discount = new CheckoutAdjustment(
            AdjustmentType::DISCOUNT,
            'core.voucher',
            'Discount',
            Money::fromCents(-200)
        );
        $handling = new CheckoutAdjustment(
            AdjustmentType::HANDLING,
            'core.handling',
            'Handling',
            Money::fromCents(100)
        );
        $collection = new AdjustmentCollection($shipping, $discount, $handling);

        $this->assertSame($expectedCents, $collection->getTotalByType($type)->getCents());
    }

    public static function getTotalByTypeProvider(): \Generator
    {
        yield 'shipping' => ['type' => AdjustmentType::SHIPPING, 'expectedCents' => 500];
        yield 'discount' => ['type' => AdjustmentType::DISCOUNT, 'expectedCents' => -200];
        yield 'handling' => ['type' => AdjustmentType::HANDLING, 'expectedCents' => 100];
        yield 'nonexistent type' => ['type' => AdjustmentType::LOYALTY, 'expectedCents' => 0];
    }

    #[Test]
    public function discountTotalIncludesBothDiscountAndLoyaltyAsPositiveMagnitude(): void
    {
        $voucher = new CheckoutAdjustment(
            AdjustmentType::DISCOUNT,
            'core.voucher',
            'Voucher',
            Money::fromCents(-300)
        );
        $loyalty = new CheckoutAdjustment(
            AdjustmentType::LOYALTY,
            'core.credit_points',
            'Loyalty',
            Money::fromCents(-150)
        );
        $shipping = new CheckoutAdjustment(
            AdjustmentType::SHIPPING,
            'core.shipping',
            'Shipping',
            Money::fromCents(500)
        );
        $collection = new AdjustmentCollection($voucher, $loyalty, $shipping);

        // Both -300 and -150 are reducing, so discount total should be positive 450
        $this->assertSame(450, $collection->getDiscountTotal()->getCents());
    }

    #[Test]
    public function netTotalIncludesOnlyTaxableAdjustments(): void
    {
        // Shipping: 595 cents gross at 0.19 => net 500, tax 95
        $shipping = new CheckoutAdjustment(
            AdjustmentType::SHIPPING,
            'core.shipping',
            'Shipping',
            Money::fromCents(595),
            0.19
        );
        // Discount: -200, no tax
        $discount = new CheckoutAdjustment(
            AdjustmentType::DISCOUNT,
            'core.voucher',
            'Discount',
            Money::fromCents(-200)
        );
        $collection = new AdjustmentCollection($shipping, $discount);

        // Only shipping contributes to net: 595 / 1.19 ≈ 500
        $this->assertSame(500, $collection->getNetTotal()->getCents());
    }

    #[Test]
    public function taxTotalIncludesOnlyTaxableAdjustments(): void
    {
        // Shipping: 595 cents gross at 0.19 => net 500, tax 95
        $shipping = new CheckoutAdjustment(
            AdjustmentType::SHIPPING,
            'core.shipping',
            'Shipping',
            Money::fromCents(595),
            0.19
        );
        // Handling: 100, no tax
        $handling = new CheckoutAdjustment(
            AdjustmentType::HANDLING,
            'core.handling',
            'Handling',
            Money::fromCents(100)
        );
        $collection = new AdjustmentCollection($shipping, $handling);

        // Only shipping contributes tax: 595 - 500 = 95
        $this->assertSame(95, $collection->getTaxTotal()->getCents());
    }

    #[Test]
    public function withReturnsNewCollectionLeavingOriginalUnchanged(): void
    {
        $original = new AdjustmentCollection(
            new CheckoutAdjustment(
                AdjustmentType::SHIPPING,
                'core.shipping',
                'Shipping',
                Money::fromCents(500)
            )
        );

        $newAdjustment = new CheckoutAdjustment(
            AdjustmentType::DISCOUNT,
            'core.voucher',
            'Discount',
            Money::fromCents(-100)
        );
        $modified = $original->with($newAdjustment);

        // Original unchanged
        $this->assertSame(500, $original->getTotal()->getCents());
        $this->assertCount(1, $original->all());

        // Modified has both
        $this->assertSame(400, $modified->getTotal()->getCents());
        $this->assertCount(2, $modified->all());

        // Are different objects
        $this->assertNotSame($original, $modified);
    }

    #[Test]
    public function byTypeReturnsOnlyAdjustmentsOfMatchingType(): void
    {
        $shipping = new CheckoutAdjustment(
            AdjustmentType::SHIPPING,
            'core.shipping',
            'Shipping',
            Money::fromCents(500)
        );
        $discount1 = new CheckoutAdjustment(
            AdjustmentType::DISCOUNT,
            'core.voucher',
            'Voucher 1',
            Money::fromCents(-100)
        );
        $discount2 = new CheckoutAdjustment(
            AdjustmentType::DISCOUNT,
            'core.voucher',
            'Voucher 2',
            Money::fromCents(-50)
        );
        $collection = new AdjustmentCollection($shipping, $discount1, $discount2);

        $discounts = $collection->byType(AdjustmentType::DISCOUNT);

        $this->assertCount(2, $discounts);
        $this->assertSame(-100, $discounts[0]->getAmount()->getCents());
        $this->assertSame(-50, $discounts[1]->getAmount()->getCents());
    }

    #[Test]
    public function allReturnsAllAdjustmentsInOrder(): void
    {
        $shipping = new CheckoutAdjustment(
            AdjustmentType::SHIPPING,
            'core.shipping',
            'Shipping',
            Money::fromCents(500)
        );
        $discount = new CheckoutAdjustment(
            AdjustmentType::DISCOUNT,
            'core.voucher',
            'Discount',
            Money::fromCents(-200)
        );
        $collection = new AdjustmentCollection($shipping, $discount);

        $all = $collection->all();

        $this->assertCount(2, $all);
        $this->assertSame($shipping, $all[0]);
        $this->assertSame($discount, $all[1]);
    }

    #[Test]
    public function multipleTaxableAdjustmentsContributeToTotals(): void
    {
        // First shipping: 595 cents at 0.19 => net 500, tax 95
        $shipping1 = new CheckoutAdjustment(
            AdjustmentType::SHIPPING,
            'provider1',
            'Shipping 1',
            Money::fromCents(595),
            0.19
        );
        // Second shipping: 1190 cents at 0.19 => net 1000, tax 190
        $shipping2 = new CheckoutAdjustment(
            AdjustmentType::SHIPPING,
            'provider2',
            'Shipping 2',
            Money::fromCents(1190),
            0.19
        );
        $collection = new AdjustmentCollection($shipping1, $shipping2);

        // Total gross: 1785
        $this->assertSame(1785, $collection->getTotal()->getCents());
        // Total net: 1500 (500 + 1000)
        $this->assertSame(1500, $collection->getNetTotal()->getCents());
        // Total tax: 285 (95 + 190)
        $this->assertSame(285, $collection->getTaxTotal()->getCents());
    }

    #[Test]
    public function negativeNonTaxableAdjustmentDoesNotContributeToNetAndTax(): void
    {
        // Discount that reduces the gross, but no tax
        $discount = new CheckoutAdjustment(
            AdjustmentType::DISCOUNT,
            'core.voucher',
            'Discount',
            Money::fromCents(-500),
            0.0  // Explicitly no tax
        );
        $collection = new AdjustmentCollection($discount);

        // Gross reduced by 500
        $this->assertSame(-500, $collection->getTotal()->getCents());
        // But net and tax are zero because taxRate is 0.0
        $this->assertSame(0, $collection->getNetTotal()->getCents());
        $this->assertSame(0, $collection->getTaxTotal()->getCents());
    }
}
