<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Voucher;

use GoldeneZeiten\Products\Domain\Dto\BasketDiscountSummary;
use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\Repository\VoucherRedemptionRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\Voucher\Exception\VoucherNotApplicableException;
use GoldeneZeiten\Products\Service\Voucher\Exception\VoucherNotFoundException;

final class VoucherService
{
    public function __construct(
        private readonly VoucherRepository $voucherRepository,
        private readonly VoucherRedemptionRepository $voucherRedemptionRepository,
    ) {}

    /**
     * @throws VoucherNotFoundException|VoucherNotApplicableException
     */
    public function resolve(string $code, Money $basketGoodsTotal, int $frontendUser): Voucher
    {
        $voucher = $this->voucherRepository->findOneByCode($code);
        if ($voucher === null) {
            throw new VoucherNotFoundException(
                sprintf('No active voucher found for code "%s".', $code),
                1751850000
            );
        }
        $this->assertApplicable($voucher, $basketGoodsTotal, $frontendUser);
        return $voucher;
    }

    /**
     * Strict counterpart to buildDiscountSummary(): used at order placement, where a code that
     * became invalid since the basket was last viewed must fail the whole placement rather than
     * be silently dropped.
     *
     * @param string[] $codes
     * @throws VoucherNotFoundException|VoucherNotApplicableException
     */
    public function resolveAllOrFail(array $codes, Money $basketGoodsTotal, int $frontendUser): BasketDiscountSummary
    {
        $vouchers = [];
        foreach ($codes as $code) {
            $vouchers[] = $this->resolve($code, $basketGoodsTotal, $frontendUser);
        }
        return new BasketDiscountSummary($vouchers, $this->calculateCombinedDiscount($vouchers, $basketGoodsTotal));
    }

    /**
     * @param Voucher[] $vouchers
     */
    public function calculateCombinedDiscount(array $vouchers, Money $basketGoodsTotal): Money
    {
        $total = Money::fromCents(0);
        foreach ($vouchers as $voucher) {
            $total = $total->add($voucher->calculateDiscount($basketGoodsTotal));
        }
        return $total->getCents() > $basketGoodsTotal->getCents() ? $basketGoodsTotal : $total;
    }

    /**
     * Resolves every code, silently dropping ones that are no longer valid (expired, exhausted, ...)
     * rather than failing the whole basket view - a stale code just stops contributing to the
     * discount until the shopper removes it.
     *
     * @param string[] $codes
     */
    public function buildDiscountSummary(array $codes, Money $basketGoodsTotal, int $frontendUser): BasketDiscountSummary
    {
        $vouchers = $this->resolveValidVouchers($codes, $basketGoodsTotal, $frontendUser);
        return new BasketDiscountSummary($vouchers, $this->calculateCombinedDiscount($vouchers, $basketGoodsTotal));
    }

    /**
     * Whether $newVoucher may join $existingVouchers: a non-combinable voucher must always be alone,
     * so any mix involving one requires clearing first.
     *
     * @param Voucher[] $existingVouchers
     */
    public function canCoexist(array $existingVouchers, Voucher $newVoucher): bool
    {
        if ($existingVouchers === []) {
            return true;
        }
        if (!$newVoucher->isCombinable()) {
            return false;
        }
        foreach ($existingVouchers as $existingVoucher) {
            if (!$existingVoucher->isCombinable()) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string[] $codes
     * @return Voucher[]
     */
    private function resolveValidVouchers(array $codes, Money $basketGoodsTotal, int $frontendUser): array
    {
        $vouchers = [];
        foreach ($codes as $code) {
            try {
                $vouchers[] = $this->resolve($code, $basketGoodsTotal, $frontendUser);
            } catch (VoucherNotFoundException|VoucherNotApplicableException) {
                continue;
            }
        }
        return $vouchers;
    }

    private function assertApplicable(Voucher $voucher, Money $basketGoodsTotal, int $frontendUser): void
    {
        if (!$voucher->isAvailableToFrontendUser($frontendUser)) {
            throw new VoucherNotApplicableException(
                sprintf('Voucher "%s" is bound to a different customer.', $voucher->getCode()),
                1751850001
            );
        }
        if (!$voucher->meetsMinimumBasketValue($basketGoodsTotal)) {
            throw new VoucherNotApplicableException(
                sprintf('Voucher "%s" requires a higher basket value.', $voucher->getCode()),
                1751850002
            );
        }
        $this->assertUsageLimitNotExceeded($voucher);
    }

    private function assertUsageLimitNotExceeded(Voucher $voucher): void
    {
        if ($voucher->getUsageLimit() > 0 && $this->voucherRedemptionRepository->countFor($voucher) >= $voucher->getUsageLimit()) {
            throw new VoucherNotApplicableException(
                sprintf('Voucher "%s" has already been used the maximum number of times.', $voucher->getCode()),
                1751850003
            );
        }
    }
}
