<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Domain\Model;

use GoldeneZeiten\Products\Core\Domain\Model\ShippingMethod;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ShippingMethodTest extends UnitTestCase
{
    #[Test]
    public function isApplicableWithNoBoundsSetIsAlwaysTrue(): void
    {
        $method = new ShippingMethod();

        $this->assertTrue($method->isApplicable(0, Money::fromDecimalString('0.00')));
        $this->assertTrue($method->isApplicable(100000, Money::fromDecimalString('100000.00')));
    }

    #[Test]
    public function tooLightForTheMinimumWeightIsNotApplicable(): void
    {
        $method = new ShippingMethod();
        $method->setMinWeight(1000);

        $this->assertFalse($method->isApplicable(999, Money::fromDecimalString('10.00')));
        $this->assertTrue($method->isApplicable(1000, Money::fromDecimalString('10.00')));
    }

    #[Test]
    public function tooHeavyForTheMaximumWeightIsNotApplicable(): void
    {
        $method = new ShippingMethod();
        $method->setMaxWeight(1000);

        $this->assertTrue($method->isApplicable(1000, Money::fromDecimalString('10.00')));
        $this->assertFalse($method->isApplicable(1001, Money::fromDecimalString('10.00')));
    }

    #[Test]
    public function belowTheMinimumOrderValueIsNotApplicable(): void
    {
        $method = new ShippingMethod();
        $method->setMinOrderValue(Money::fromDecimalString('50.00'));

        $this->assertFalse($method->isApplicable(0, Money::fromDecimalString('49.99')));
        $this->assertTrue($method->isApplicable(0, Money::fromDecimalString('50.00')));
    }

    #[Test]
    public function aboveTheMaximumOrderValueIsNotApplicable(): void
    {
        $method = new ShippingMethod();
        $method->setMaxOrderValue(Money::fromDecimalString('50.00'));

        $this->assertTrue($method->isApplicable(0, Money::fromDecimalString('50.00')));
        $this->assertFalse($method->isApplicable(0, Money::fromDecimalString('50.01')));
    }
}
