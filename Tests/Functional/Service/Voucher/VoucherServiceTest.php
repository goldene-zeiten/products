<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Voucher;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\Voucher\Exception\VoucherNotApplicableException;
use GoldeneZeiten\Products\Service\Voucher\Exception\VoucherNotFoundException;
use GoldeneZeiten\Products\Service\Voucher\VoucherService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class VoucherServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private VoucherService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/vouchers.csv');
        $this->subject = $this->get(VoucherService::class);
    }

    #[Test]
    public function resolvesAValidVoucher(): void
    {
        $voucher = $this->subject->resolve('SAVE10', Money::fromDecimalString('100.00'), 1);

        $this->assertSame('SAVE10', $voucher->getCode());
    }

    #[Test]
    public function throwsForAnUnknownCode(): void
    {
        $this->expectException(VoucherNotFoundException::class);
        $this->expectExceptionCode(1751850000);

        $this->subject->resolve('DOES-NOT-EXIST', Money::fromDecimalString('100.00'), 1);
    }

    #[Test]
    public function anExpiredVoucherIsTreatedAsNotFound(): void
    {
        $this->expectException(VoucherNotFoundException::class);

        $this->subject->resolve('EXPIRED', Money::fromDecimalString('100.00'), 1);
    }

    #[Test]
    public function throwsWhenBoundToADifferentCustomer(): void
    {
        $this->expectException(VoucherNotApplicableException::class);
        $this->expectExceptionCode(1751850001);

        $this->subject->resolve('VIPONLY', Money::fromDecimalString('100.00'), 1);
    }

    #[Test]
    public function resolvesForTheBoundCustomer(): void
    {
        $voucher = $this->subject->resolve('VIPONLY', Money::fromDecimalString('100.00'), 42);

        $this->assertSame('VIPONLY', $voucher->getCode());
    }

    #[Test]
    public function throwsWhenBelowTheMinimumBasketValue(): void
    {
        $this->expectException(VoucherNotApplicableException::class);
        $this->expectExceptionCode(1751850002);

        $this->subject->resolve('BIGORDER', Money::fromDecimalString('100.00'), 1);
    }

    #[Test]
    public function throwsWhenUsageLimitIsAlreadyReached(): void
    {
        $this->expectException(VoucherNotApplicableException::class);
        $this->expectExceptionCode(1751850003);

        $this->subject->resolve('LIMITED', Money::fromDecimalString('100.00'), 1);
    }

    #[Test]
    public function calculatesCombinedDiscountCappedAtBasketTotal(): void
    {
        $save10 = $this->subject->resolve('SAVE10', Money::fromDecimalString('20.00'), 1);
        $flat5 = $this->subject->resolve('FLAT5', Money::fromDecimalString('20.00'), 1);

        $discount = $this->subject->calculateCombinedDiscount([$save10, $flat5], Money::fromDecimalString('20.00'));

        // 10% of 20.00 (2.00) + 5.00 fixed = 7.00, well under the 20.00 cap
        $this->assertSame(700, $discount->getCents());
    }

    #[Test]
    public function combinedDiscountNeverExceedsBasketTotal(): void
    {
        $save10 = $this->subject->resolve('SAVE10', Money::fromDecimalString('4.00'), 1);
        $flat5 = $this->subject->resolve('FLAT5', Money::fromDecimalString('4.00'), 1);

        $discount = $this->subject->calculateCombinedDiscount([$save10, $flat5], Money::fromDecimalString('4.00'));

        $this->assertSame(400, $discount->getCents());
    }

    #[Test]
    public function nonCombinableVoucherIsBlockedWhenBasketAlreadyHasADiscount(): void
    {
        $this->expectException(VoucherNotApplicableException::class);
        $this->expectExceptionCode(1783760128);

        $this->subject->resolve('FLAT5', Money::fromDecimalString('100.00'), 1, basketAlreadyDiscounted: true);
    }

    #[Test]
    public function combinableVoucherIsNotBlockedWhenBasketAlreadyHasADiscount(): void
    {
        $voucher = $this->subject->resolve('SAVE10', Money::fromDecimalString('100.00'), 1, basketAlreadyDiscounted: true);

        $this->assertSame('SAVE10', $voucher->getCode());
    }

    #[Test]
    public function nonCombinableVoucherIsAllowedWhenBasketHasNoExistingDiscount(): void
    {
        $voucher = $this->subject->resolve('FLAT5', Money::fromDecimalString('100.00'), 1, basketAlreadyDiscounted: false);

        $this->assertSame('FLAT5', $voucher->getCode());
    }

    #[Test]
    public function combinableVouchersCanCoexist(): void
    {
        $save10 = $this->subject->resolve('SAVE10', Money::fromDecimalString('100.00'), 1);

        $this->assertTrue($this->subject->canCoexist([$save10], $save10));
    }

    #[Test]
    public function nonCombinableVoucherCannotJoinExistingOnes(): void
    {
        $save10 = $this->subject->resolve('SAVE10', Money::fromDecimalString('100.00'), 1);
        $flat5 = $this->subject->resolve('FLAT5', Money::fromDecimalString('100.00'), 1);

        $this->assertFalse($this->subject->canCoexist([$save10], $flat5));
    }

    #[Test]
    public function anythingCoexistsWithAnEmptyList(): void
    {
        $flat5 = $this->subject->resolve('FLAT5', Money::fromDecimalString('100.00'), 1);

        $this->assertTrue($this->subject->canCoexist([], $flat5));
    }
}
