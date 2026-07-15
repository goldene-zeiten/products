<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Tests\Unit\Domain\Model;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Voucher\Domain\Enum\VoucherDiscountType;
use GoldeneZeiten\Products\Voucher\Domain\Model\Voucher;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class VoucherTest extends UnitTestCase
{
    #[Test]
    public function percentageDiscountIsCalculatedFromTheBasketTotal(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::PERCENTAGE, '10.00');

        $this->assertSame(1000, $voucher->calculateDiscount(Money::fromDecimalString('100.00'))->getCents());
    }

    #[Test]
    public function fixedDiscountIgnoresTheBasketTotal(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::FIXED, '5.00');

        $this->assertSame(500, $voucher->calculateDiscount(Money::fromDecimalString('100.00'))->getCents());
    }

    #[Test]
    public function fixedDiscountIsCappedAtTheBasketTotal(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::FIXED, '50.00');

        $this->assertSame(2000, $voucher->calculateDiscount(Money::fromDecimalString('20.00'))->getCents());
    }

    #[Test]
    public function meetsMinimumBasketValueIsTrueWhenNoMinimumSet(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::PERCENTAGE, '10.00');

        $this->assertTrue($voucher->meetsMinimumBasketValue(Money::fromDecimalString('0.01')));
    }

    #[Test]
    public function meetsMinimumBasketValueFailsBelowTheConfiguredMinimum(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::PERCENTAGE, '10.00');
        $voucher->setMinBasketValue(Money::fromDecimalString('50.00'));

        $this->assertFalse($voucher->meetsMinimumBasketValue(Money::fromDecimalString('49.99')));
        $this->assertTrue($voucher->meetsMinimumBasketValue(Money::fromDecimalString('50.00')));
    }

    #[Test]
    public function unboundVoucherIsAvailableToAnyone(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::PERCENTAGE, '10.00');

        $this->assertTrue($voucher->isAvailableToFrontendUser(0));
        $this->assertTrue($voucher->isAvailableToFrontendUser(42));
    }

    #[Test]
    public function boundVoucherIsOnlyAvailableToThatCustomer(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::PERCENTAGE, '10.00');
        $voucher->setBoundFrontendUser(42);

        $this->assertTrue($voucher->isAvailableToFrontendUser(42));
        $this->assertFalse($voucher->isAvailableToFrontendUser(1));
    }

    #[Test]
    public function waivesShippingCostDefaultsToFalse(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::PERCENTAGE, '10.00');

        $this->assertFalse($voucher->isWaivingShippingCost());
    }

    #[Test]
    public function waivesShippingCostCanBeSet(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::PERCENTAGE, '10.00');
        $voucher->setWaivesShippingCost(true);

        $this->assertTrue($voucher->isWaivingShippingCost());
    }

    #[Test]
    public function generatedFromOrderDefaultsToZero(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::PERCENTAGE, '10.00');

        $this->assertSame(0, $voucher->getGeneratedFromOrder());
    }

    private function voucher(VoucherDiscountType $type, string $discountValue): Voucher
    {
        $voucher = new Voucher();
        $voucher->setDiscountType($type);
        $voucher->setDiscountValue($discountValue);
        return $voucher;
    }
}
