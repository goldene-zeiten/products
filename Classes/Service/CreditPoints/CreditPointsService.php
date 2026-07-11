<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\CreditPoints;

use GoldeneZeiten\Products\Configuration\CreditPointsConfiguration;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\CreditPointsRedemption;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\CreditPoints\Exception\InsufficientCreditPointsException;

/**
 * Balance reads/writes are delegated to CreditPointsBalanceService, which maintains a running
 * total atomically guarded against concurrent over-spend (see CreditPointsBalanceService's own
 * docblock) - the ledger table remains the permanent audit trail. Stateless by design - takes an
 * already-resolved CreditPointsConfiguration rather than reading settings itself, so it's a pure
 * function of its inputs (see CreditPointsConfiguration's docblock).
 */
final class CreditPointsService
{
    public function __construct(
        private readonly CreditPointsBalanceService $creditPointsBalanceService
    ) {}

    public function getBalance(int $frontendUser): int
    {
        return $this->creditPointsBalanceService->getBalance($frontendUser);
    }

    /**
     * Article inherits the product's earning rate, there is no per-article override. In
     * "basketTiered" mode the whole order earns a single highest-qualifying tier's points
     * instead, mirroring legacy tx_ttproducts_creditpoints_div::getCreditPoints() (krsort by
     * threshold, first match at or below the basket total wins - not summed across tiers). In
     * "autoPriceFactor" mode, a line without its own explicit creditPoints value earns points via
     * a flat price->points conversion instead of 0, mirroring legacy's "auto" earning mode.
     */
    public function calculateEarnedPoints(BasketViewModel $basket, CreditPointsConfiguration $configuration): int
    {
        return match ($configuration->getEarningMode()) {
            'basketTiered' => $this->calculateTieredPoints($basket->getTotalGross(), $configuration),
            'autoPriceFactor' => $this->calculateAutoPriceFactorPoints($basket, $configuration),
            default => $this->calculatePerProductPoints($basket),
        };
    }

    private function calculatePerProductPoints(BasketViewModel $basket): int
    {
        $points = 0;
        foreach ($basket->getItems() as $item) {
            $points += $item->getProduct()->getCreditPoints() * $item->getQuantity();
        }
        return $points;
    }

    /**
     * A line's own explicit creditPoints value always wins, even in this mode - only lines with
     * none (0) fall back to the price->points conversion.
     */
    private function calculateAutoPriceFactorPoints(BasketViewModel $basket, CreditPointsConfiguration $configuration): int
    {
        $priceFactor = $configuration->getPriceFactor();
        $points = 0;
        foreach ($basket->getItems() as $item) {
            $explicitPoints = $item->getProduct()->getCreditPoints();
            $points += $explicitPoints > 0
                ? $explicitPoints * $item->getQuantity()
                : (int)floor($item->getLineTotalGross()->getCents() / 100 * $priceFactor);
        }
        return $points;
    }

    private function calculateTieredPoints(Money $basketTotal, CreditPointsConfiguration $configuration): int
    {
        $points = 0;
        $bestThresholdCents = -1;
        foreach ($configuration->getEarningTiers() as $tier) {
            $thresholdCents = $tier->getThreshold()->getCents();
            if ($basketTotal->getCents() >= $thresholdCents && $thresholdCents > $bestThresholdCents) {
                $bestThresholdCents = $thresholdCents;
                $points = $tier->getPoints();
            }
        }
        return $points;
    }

    public function calculateRedemptionValue(int $points, CreditPointsConfiguration $configuration): Money
    {
        return $configuration->getMoneyPerPoint()->multiply($points);
    }

    /**
     * @throws InsufficientCreditPointsException
     */
    public function assertSpendable(int $frontendUser, int $requestedPoints): void
    {
        $balance = $this->getBalance($frontendUser);
        if ($requestedPoints > $balance) {
            throw new InsufficientCreditPointsException(
                sprintf('Requested %d credit points but only %d are available.', $requestedPoints, $balance),
                1783430000
            );
        }
    }

    /**
     * Clamps to whichever is lower: the current balance or what the basket total can absorb -
     * the same double-cap idea as legacy's max1/max2. Guests (frontend_user 0) never redeem.
     */
    public function redeem(int $frontendUser, int $requestedPoints, Money $basketGoodsTotal, CreditPointsConfiguration $configuration): CreditPointsRedemption
    {
        $points = $this->clampRedeemablePoints($frontendUser, $requestedPoints, $basketGoodsTotal, $configuration);
        return new CreditPointsRedemption($points, $this->calculateRedemptionValue($points, $configuration));
    }

    private function clampRedeemablePoints(int $frontendUser, int $requestedPoints, Money $basketGoodsTotal, CreditPointsConfiguration $configuration): int
    {
        $moneyPerPointCents = $configuration->getMoneyPerPoint()->getCents();
        if (!$configuration->isEnabled() || $requestedPoints <= 0 || $frontendUser === 0 || $moneyPerPointCents <= 0) {
            return 0;
        }
        $maxByBasketValue = intdiv($basketGoodsTotal->getCents(), $moneyPerPointCents);
        return max(0, min($requestedPoints, $this->getBalance($frontendUser), $maxByBasketValue));
    }
}
