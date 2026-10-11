<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Service;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Voucher\Domain\Dto\BasketDiscountSummary;
use GoldeneZeiten\Products\Voucher\Domain\Model\Voucher;
use GoldeneZeiten\Products\Voucher\Domain\Repository\VoucherRedemptionRepository;
use GoldeneZeiten\Products\Voucher\Domain\Repository\VoucherRepository;
use GoldeneZeiten\Products\Voucher\Service\Exception\VoucherNotApplicableException;
use GoldeneZeiten\Products\Voucher\Service\Exception\VoucherNotFoundException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class VoucherService
{
    public function __construct(
        private readonly VoucherRepository $voucherRepository,
        private readonly VoucherRedemptionRepository $voucherRedemptionRepository,
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @throws VoucherNotFoundException|VoucherNotApplicableException
     */
    public function resolve(string $code, Money $basketGoodsTotal, int $frontendUser, bool $basketAlreadyDiscounted = false): Voucher
    {
        $voucher = $this->voucherRepository->findOneByCode($code);
        if ($voucher === null) {
            throw new VoucherNotFoundException(
                sprintf('No active voucher found for code "%s".', $code),
                1751850000
            );
        }
        $this->assertApplicable($voucher, $basketGoodsTotal, $frontendUser, $basketAlreadyDiscounted);
        return $voucher;
    }

    /**
     * The vouchers an order is placed with: every one must still apply, the same way the basket checked
     * it when the code was entered - including that a voucher which does not combine stands alone, on
     * goods that are not discounted already.
     *
     * @param string[] $codes
     * @throws VoucherNotFoundException|VoucherNotApplicableException
     */
    public function resolveAllOrFail(array $codes, Money $basketGoodsTotal, int $frontendUser, bool $basketAlreadyDiscounted = false): BasketDiscountSummary
    {
        $vouchers = [];
        foreach ($codes as $code) {
            $vouchers[] = $this->resolve($code, $basketGoodsTotal, $frontendUser, $basketAlreadyDiscounted);
        }
        $this->assertCombinable($vouchers);
        return $this->summarize($vouchers, $basketGoodsTotal);
    }

    /**
     * @param Voucher[] $vouchers
     */
    public function calculateCombinedDiscount(array $vouchers, Money $basketGoodsTotal): Money
    {
        return $this->summarize($vouchers, $basketGoodsTotal)->getDiscountTotal();
    }

    /**
     * @param string[] $codes
     */
    public function buildDiscountSummary(array $codes, Money $basketGoodsTotal, int $frontendUser): BasketDiscountSummary
    {
        return $this->summarize($this->resolveValidVouchers($codes, $basketGoodsTotal, $frontendUser), $basketGoodsTotal);
    }

    /**
     * Each voucher takes its discount off what the vouchers before it left of the goods, so together they
     * never take off more than the goods cost, and each one's share is what it really took.
     *
     * @param Voucher[] $vouchers
     */
    private function summarize(array $vouchers, Money $basketGoodsTotal): BasketDiscountSummary
    {
        $remaining = $basketGoodsTotal;
        $shares = [];
        foreach ($vouchers as $voucher) {
            $discount = $voucher->calculateDiscount($basketGoodsTotal);
            $share = $discount->getCents() > $remaining->getCents() ? $remaining : $discount;
            $shares[] = $share;
            $remaining = $remaining->subtract($share);
        }
        return new BasketDiscountSummary($vouchers, $basketGoodsTotal->subtract($remaining), $shares);
    }

    /**
     * @param Voucher[] $vouchers
     * @throws VoucherNotApplicableException
     */
    private function assertCombinable(array $vouchers): void
    {
        if (count($vouchers) < 2) {
            return;
        }
        foreach ($vouchers as $voucher) {
            if (!$voucher->isCombinable()) {
                throw new VoucherNotApplicableException(
                    sprintf('Voucher "%s" cannot be combined with other vouchers.', $voucher->getCode()),
                    1791667564
                );
            }
        }
    }

    /**
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

    private function assertApplicable(Voucher $voucher, Money $basketGoodsTotal, int $frontendUser, bool $basketAlreadyDiscounted): void
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
        $this->assertNotBlockedByExistingDiscount($voucher, $basketAlreadyDiscounted);
    }

    private function assertNotBlockedByExistingDiscount(Voucher $voucher, bool $basketAlreadyDiscounted): void
    {
        if ($basketAlreadyDiscounted && !$voucher->isCombinable()) {
            throw new VoucherNotApplicableException(
                sprintf('Voucher "%s" cannot be combined with the basket\'s existing discount.', $voucher->getCode()),
                1783760128
            );
        }
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

    /**
     * Atomic UPDATE...WHERE guard against concurrent over-redemption.
     *
     * @throws VoucherNotApplicableException
     * @see \GoldeneZeiten\Products\Core\Service\Order\StockService::decrementForItem()
     */
    public function redeemAtomically(Voucher $voucher): void
    {
        $usageLimit = $voucher->getUsageLimit();
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_voucher');
        $queryBuilder->update('tx_products_domain_model_voucher')
            ->set('redemption_count', $queryBuilder->quoteIdentifier('redemption_count') . ' + 1', false)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($voucher->getUid(), Connection::PARAM_INT)));
        if ($usageLimit > 0) {
            $queryBuilder->andWhere($queryBuilder->expr()->lt('redemption_count', $queryBuilder->createNamedParameter($usageLimit, Connection::PARAM_INT)));
        }
        $affectedRows = $queryBuilder->executeStatement();

        if ($affectedRows === 0) {
            throw new VoucherNotApplicableException(
                sprintf('Voucher "%s" has already been used the maximum number of times.', $voucher->getCode()),
                1751850004
            );
        }
    }
}
