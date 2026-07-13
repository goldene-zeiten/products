<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Pricing;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Pricing\Unit\UnitRegistry;
use GoldeneZeiten\Products\Pricing\UnitPriceCalculator;
use PHPUnit\Framework\TestCase;

final class UnitPriceCalculatorTest extends TestCase
{
    private UnitPriceCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new UnitPriceCalculator(new UnitRegistry());
    }

    public function testCalculateG2Kg(): void
    {
        $price = Money::fromCents(250); // 2.50
        $unitPrice = $this->calculator->calculate($price, 500, 'g'); // 500g

        $this->assertNotNull($unitPrice);
        $this->assertSame('kg', $unitPrice->referenceUnitLabel);
        $this->assertSame(500, $unitPrice->price->getCents()); // 2.50 for 500g = 5.00 per kg
    }

    public function testCalculateKg2Kg(): void
    {
        $price = Money::fromCents(1000); // 10.00
        $unitPrice = $this->calculator->calculate($price, 1, 'kg');

        $this->assertNotNull($unitPrice);
        $this->assertSame('kg', $unitPrice->referenceUnitLabel);
        $this->assertSame(1000, $unitPrice->price->getCents()); // 10.00 per kg
    }

    public function testCalculateMl2L(): void
    {
        $price = Money::fromCents(100); // 1.00
        $unitPrice = $this->calculator->calculate($price, 500, 'ml'); // 500ml

        $this->assertNotNull($unitPrice);
        $this->assertSame('l', $unitPrice->referenceUnitLabel);
        $this->assertSame(200, $unitPrice->price->getCents()); // 2.00 per liter
    }

    public function testCalculateL2L(): void
    {
        $price = Money::fromCents(300); // 3.00
        $unitPrice = $this->calculator->calculate($price, 1, 'l');

        $this->assertNotNull($unitPrice);
        $this->assertSame('l', $unitPrice->referenceUnitLabel);
        $this->assertSame(300, $unitPrice->price->getCents()); // 3.00 per liter
    }

    public function testCalculateOz2Lb(): void
    {
        $price = Money::fromCents(150); // 1.50
        $unitPrice = $this->calculator->calculate($price, 8, 'oz'); // 8 oz

        $this->assertNotNull($unitPrice);
        $this->assertSame('lb', $unitPrice->referenceUnitLabel);
        // 8 oz = 226.796... grams, 1 lb = 453.59237 grams
        // Price per gram: 1.50 / 226.796... = 0.00661... per gram
        // Price per lb: 0.00661... * 453.59237 = 2.99... (around 300 cents)
        $this->assertSame(300, $unitPrice->price->getCents());
    }

    public function testCalculateFlOz2Gal(): void
    {
        $price = Money::fromCents(100); // 1.00
        $unitPrice = $this->calculator->calculate($price, 32, 'fl_oz'); // 32 fl oz = 1 quart

        $this->assertNotNull($unitPrice);
        $this->assertSame('gal', $unitPrice->referenceUnitLabel);
        // 32 fl oz = 32 * 29.5735295625 = 947.088... ml
        // 1 gal = 3785.411784 ml
        // Price per ml: 1.00 / 947.088... = 0.001056... per ml
        // Price per gal: 0.001056... * 3785.411784 = 3.99... (around 400 cents)
        $this->assertSame(400, $unitPrice->price->getCents());
    }

    public function testCalculateIn2Ft(): void
    {
        $price = Money::fromCents(150); // 1.50
        $unitPrice = $this->calculator->calculate($price, 6, 'in'); // 6 inches = 0.5 feet

        $this->assertNotNull($unitPrice);
        $this->assertSame('ft', $unitPrice->referenceUnitLabel);
        // 6 inches = 6 * 25.4 = 152.4 mm, 1 ft = 304.8 mm
        // Price per mm: 1.50 / 152.4 = 0.00985... per mm
        // Price per ft: 0.00985... * 304.8 = 3.00 per foot
        $this->assertSame(300, $unitPrice->price->getCents());
    }

    public function testCalculateFt(): void
    {
        $price = Money::fromCents(500); // 5.00
        $unitPrice = $this->calculator->calculate($price, 1, 'ft');

        $this->assertNotNull($unitPrice);
        $this->assertSame('ft', $unitPrice->referenceUnitLabel);
        $this->assertSame(500, $unitPrice->price->getCents()); // 5.00 per foot
    }

    public function testCalculateFt2(): void
    {
        $price = Money::fromCents(1000); // 10.00
        $unitPrice = $this->calculator->calculate($price, 10, 'ft2'); // 10 square feet

        $this->assertNotNull($unitPrice);
        $this->assertSame('ft2', $unitPrice->referenceUnitLabel);
        $this->assertSame(100, $unitPrice->price->getCents()); // 1.00 per ft²
    }

    public function testCalculateM2(): void
    {
        $price = Money::fromCents(5000); // 50.00
        $unitPrice = $this->calculator->calculate($price, 10, 'm2'); // 10 m²

        $this->assertNotNull($unitPrice);
        $this->assertSame('m2', $unitPrice->referenceUnitLabel);
        $this->assertSame(500, $unitPrice->price->getCents()); // 5.00 per m²
    }

    public function testUnknownUnitReturnsNull(): void
    {
        $price = Money::fromCents(100);
        $unitPrice = $this->calculator->calculate($price, 100, 'unknown');

        $this->assertNull($unitPrice);
    }

    public function testZeroContentAmountReturnsNull(): void
    {
        $price = Money::fromCents(100);
        $unitPrice = $this->calculator->calculate($price, 0, 'g');

        $this->assertNull($unitPrice);
    }

    public function testNegativeContentAmountReturnsNull(): void
    {
        $price = Money::fromCents(100);
        $unitPrice = $this->calculator->calculate($price, -10, 'g');

        $this->assertNull($unitPrice);
    }

    public function testEmptyUnitReturnsNull(): void
    {
        $price = Money::fromCents(100);
        $unitPrice = $this->calculator->calculate($price, 100, '');

        $this->assertNull($unitPrice);
    }
}
