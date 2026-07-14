<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Voucher;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\Voucher\Exception\VoucherNotApplicableException;
use GoldeneZeiten\Products\Core\Service\Voucher\Exception\VoucherNotFoundException;
use GoldeneZeiten\Products\Core\Service\Voucher\VoucherService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class VoucherServiceTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/VoucherServiceTest/vouchers.csv');
    }

    #[Test]
    #[DataProvider('resolvesValidVoucherProvider')]
    public function resolveReturnsTheVoucherForValidCodes(string $code, int $frontendUserUid): void
    {
        $subject = $this->get(VoucherService::class);
        $voucher = $subject->resolve($code, Money::fromDecimalString('100.00'), $frontendUserUid);

        $this->assertSame($code, $voucher->getCode());
    }

    public static function resolvesValidVoucherProvider(): \Generator
    {
        yield 'resolves a valid voucher' => ['code' => 'SAVE10', 'frontendUserUid' => 1];
        yield 'resolves for the bound customer' => ['code' => 'VIPONLY', 'frontendUserUid' => 42];
    }

    #[Test]
    #[DataProvider('notFoundProvider')]
    public function resolveThrowsNotFoundInVariousScenarios(string $code, ?int $expectedExceptionCode): void
    {
        $subject = $this->get(VoucherService::class);
        $this->expectException(VoucherNotFoundException::class);
        if ($expectedExceptionCode !== null) {
            $this->expectExceptionCode($expectedExceptionCode);
        }

        $subject->resolve($code, Money::fromDecimalString('100.00'), 1);
    }

    public static function notFoundProvider(): \Generator
    {
        yield 'an unknown code' => ['code' => 'DOES-NOT-EXIST', 'expectedExceptionCode' => 1751850000];
        yield 'an expired voucher is treated as not found' => ['code' => 'EXPIRED', 'expectedExceptionCode' => null];
    }

    #[Test]
    #[DataProvider('notApplicableProvider')]
    public function resolveThrowsNotApplicableInVariousScenarios(string $code, int $frontendUserUid, bool $basketAlreadyDiscounted, int $expectedExceptionCode): void
    {
        $subject = $this->get(VoucherService::class);
        $this->expectException(VoucherNotApplicableException::class);
        $this->expectExceptionCode($expectedExceptionCode);

        $subject->resolve($code, Money::fromDecimalString('100.00'), $frontendUserUid, basketAlreadyDiscounted: $basketAlreadyDiscounted);
    }

    public static function notApplicableProvider(): \Generator
    {
        yield 'bound to a different customer' => ['code' => 'VIPONLY', 'frontendUserUid' => 1, 'basketAlreadyDiscounted' => false, 'expectedExceptionCode' => 1751850001];
        yield 'below the minimum basket value' => ['code' => 'BIGORDER', 'frontendUserUid' => 1, 'basketAlreadyDiscounted' => false, 'expectedExceptionCode' => 1751850002];
        yield 'usage limit is already reached' => ['code' => 'LIMITED', 'frontendUserUid' => 1, 'basketAlreadyDiscounted' => false, 'expectedExceptionCode' => 1751850003];
        yield 'non-combinable voucher is blocked when basket already has a discount' => ['code' => 'FLAT5', 'frontendUserUid' => 1, 'basketAlreadyDiscounted' => true, 'expectedExceptionCode' => 1783760128];
    }

    #[Test]
    #[DataProvider('combinedDiscountProvider')]
    public function combinedDiscountIsCappedAtTheBasketTotal(string $basketTotal, int $expectedCents): void
    {
        $subject = $this->get(VoucherService::class);
        $save10 = $subject->resolve('SAVE10', Money::fromDecimalString($basketTotal), 1);
        $flat5 = $subject->resolve('FLAT5', Money::fromDecimalString($basketTotal), 1);

        $discount = $subject->calculateCombinedDiscount([$save10, $flat5], Money::fromDecimalString($basketTotal));

        $this->assertSame($expectedCents, $discount->getCents());
    }

    public static function combinedDiscountProvider(): \Generator
    {
        // 10% of 20.00 (2.00) + 5.00 fixed = 7.00, well under the 20.00 cap
        yield 'calculates the combined discount below the cap' => ['basketTotal' => '20.00', 'expectedCents' => 700];
        yield 'never exceeds the basket total' => ['basketTotal' => '4.00', 'expectedCents' => 400];
    }

    #[Test]
    #[DataProvider('resolveAllowedWithDiscountStateProvider')]
    public function resolveSucceedsAccordingToCombinabilityAndExistingDiscount(string $code, bool $basketAlreadyDiscounted): void
    {
        $subject = $this->get(VoucherService::class);
        $voucher = $subject->resolve($code, Money::fromDecimalString('100.00'), 1, basketAlreadyDiscounted: $basketAlreadyDiscounted);

        $this->assertSame($code, $voucher->getCode());
    }

    public static function resolveAllowedWithDiscountStateProvider(): \Generator
    {
        yield 'combinable voucher is not blocked when basket already has a discount' => ['code' => 'SAVE10', 'basketAlreadyDiscounted' => true];
        yield 'non-combinable voucher is allowed when basket has no existing discount' => ['code' => 'FLAT5', 'basketAlreadyDiscounted' => false];
    }

    #[Test]
    public function combinableVouchersCanCoexist(): void
    {
        $subject = $this->get(VoucherService::class);
        $save10 = $subject->resolve('SAVE10', Money::fromDecimalString('100.00'), 1);

        $this->assertTrue($subject->canCoexist([$save10], $save10));
    }

    #[Test]
    public function nonCombinableVoucherCannotJoinExistingOnes(): void
    {
        $subject = $this->get(VoucherService::class);
        $save10 = $subject->resolve('SAVE10', Money::fromDecimalString('100.00'), 1);
        $flat5 = $subject->resolve('FLAT5', Money::fromDecimalString('100.00'), 1);

        $this->assertFalse($subject->canCoexist([$save10], $flat5));
    }

    #[Test]
    public function anythingCoexistsWithAnEmptyList(): void
    {
        $subject = $this->get(VoucherService::class);
        $flat5 = $subject->resolve('FLAT5', Money::fromDecimalString('100.00'), 1);

        $this->assertTrue($subject->canCoexist([], $flat5));
    }
}
