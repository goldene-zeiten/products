<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Domain\Model;

use GoldeneZeiten\Products\Domain\Enum\VoucherDiscountType;
use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class VoucherTest extends UnitTestCase
{
    #[Test]
    public function percentageDiscountIsCalculatedFromTheBasketTotal(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::PERCENTAGE, '10.00');

        self::assertSame(1000, $voucher->calculateDiscount(Money::fromDecimalString('100.00'))->getCents());
    }

    #[Test]
    public function fixedDiscountIgnoresTheBasketTotal(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::FIXED, '5.00');

        self::assertSame(500, $voucher->calculateDiscount(Money::fromDecimalString('100.00'))->getCents());
    }

    #[Test]
    public function fixedDiscountIsCappedAtTheBasketTotal(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::FIXED, '50.00');

        self::assertSame(2000, $voucher->calculateDiscount(Money::fromDecimalString('20.00'))->getCents());
    }

    #[Test]
    public function meetsMinimumBasketValueIsTrueWhenNoMinimumSet(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::PERCENTAGE, '10.00');

        self::assertTrue($voucher->meetsMinimumBasketValue(Money::fromDecimalString('0.01')));
    }

    #[Test]
    public function meetsMinimumBasketValueFailsBelowTheConfiguredMinimum(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::PERCENTAGE, '10.00');
        $voucher->setMinBasketValue(Money::fromDecimalString('50.00'));

        self::assertFalse($voucher->meetsMinimumBasketValue(Money::fromDecimalString('49.99')));
        self::assertTrue($voucher->meetsMinimumBasketValue(Money::fromDecimalString('50.00')));
    }

    #[Test]
    public function unboundVoucherIsAvailableToAnyone(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::PERCENTAGE, '10.00');

        self::assertTrue($voucher->isAvailableToFrontendUser(0));
        self::assertTrue($voucher->isAvailableToFrontendUser(42));
    }

    #[Test]
    public function boundVoucherIsOnlyAvailableToThatCustomer(): void
    {
        $voucher = $this->voucher(VoucherDiscountType::PERCENTAGE, '10.00');
        $voucher->setBoundFrontendUser(42);

        self::assertTrue($voucher->isAvailableToFrontendUser(42));
        self::assertFalse($voucher->isAvailableToFrontendUser(1));
    }

    private function voucher(VoucherDiscountType $type, string $discountValue): Voucher
    {
        $voucher = new Voucher();
        $voucher->setDiscountType($type);
        $voucher->setDiscountValue($discountValue);
        return $voucher;
    }
}
