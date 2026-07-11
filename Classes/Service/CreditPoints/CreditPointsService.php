<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\CreditPoints;

use Doctrine\DBAL\ParameterType;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\CreditPointsEarningTier;
use GoldeneZeiten\Products\Domain\Dto\CreditPointsRedemption;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\CreditPoints\Exception\InsufficientCreditPointsException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Balance is always derived by summing tx_products_domain_model_creditpointstransaction rather
 * than stored on a mutable column, avoiding a race condition under concurrent checkouts (same
 * reasoning as the voucher redemption log).
 */
final class CreditPointsService
{
    private const TABLE = 'tx_products_domain_model_creditpointstransaction';

    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        ConfigurationManagerInterface $configurationManager
    ) {
        $this->settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
    }

    public function isEnabled(): bool
    {
        return (bool)($this->settings['creditPoints']['enabled'] ?? false);
    }

    public function getMoneyPerPoint(): Money
    {
        return Money::fromDecimalString((string)($this->settings['creditPoints']['moneyPerPoint'] ?? '0.10'));
    }

    public function getBalance(int $frontendUser): int
    {
        if ($frontendUser === 0) {
            return 0;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $sum = $queryBuilder
            ->selectLiteral('SUM(' . $queryBuilder->quoteIdentifier('points') . ') AS balance')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('frontend_user', $queryBuilder->createNamedParameter($frontendUser, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchOne();
        return (int)($sum ?? 0);
    }

    /**
     * Article inherits the product's earning rate, there is no per-article override. In
     * "basketTiered" mode the whole order earns a single highest-qualifying tier's points
     * instead, mirroring legacy tx_ttproducts_creditpoints_div::getCreditPoints() (krsort by
     * threshold, first match at or below the basket total wins - not summed across tiers). In
     * "autoPriceFactor" mode, a line without its own explicit creditPoints value earns points via
     * a flat price->points conversion instead of 0, mirroring legacy's "auto" earning mode.
     */
    public function calculateEarnedPoints(BasketViewModel $basket): int
    {
        return match ($this->earningMode()) {
            'basketTiered' => $this->calculateTieredPoints($basket->getTotalGross()),
            'autoPriceFactor' => $this->calculateAutoPriceFactorPoints($basket),
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
    private function calculateAutoPriceFactorPoints(BasketViewModel $basket): int
    {
        $priceFactor = $this->priceFactor();
        $points = 0;
        foreach ($basket->getItems() as $item) {
            $explicitPoints = $item->getProduct()->getCreditPoints();
            $points += $explicitPoints > 0
                ? $explicitPoints * $item->getQuantity()
                : (int)floor($item->getLineTotalGross()->getCents() / 100 * $priceFactor);
        }
        return $points;
    }

    private function priceFactor(): float
    {
        return (float)($this->settings['creditPoints']['priceFactor'] ?? 0.0);
    }

    private function earningMode(): string
    {
        return (string)($this->settings['creditPoints']['earningMode'] ?? 'perProduct');
    }

    private function calculateTieredPoints(Money $basketTotal): int
    {
        $points = 0;
        $bestThresholdCents = -1;
        foreach ($this->earningTiers() as $tier) {
            $thresholdCents = $tier->getThreshold()->getCents();
            if ($basketTotal->getCents() >= $thresholdCents && $thresholdCents > $bestThresholdCents) {
                $bestThresholdCents = $thresholdCents;
                $points = $tier->getPoints();
            }
        }
        return $points;
    }

    /**
     * @return CreditPointsEarningTier[]
     */
    private function earningTiers(): array
    {
        $tiers = [];
        foreach ((array)($this->settings['creditPoints']['earningTiers'] ?? []) as $entry) {
            $parts = explode(':', (string)$entry, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $tiers[] = new CreditPointsEarningTier(Money::fromDecimalString(trim($parts[0])), (int)trim($parts[1]));
        }
        return $tiers;
    }

    public function calculateRedemptionValue(int $points): Money
    {
        return $this->getMoneyPerPoint()->multiply($points);
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
    public function redeem(int $frontendUser, int $requestedPoints, Money $basketGoodsTotal): CreditPointsRedemption
    {
        $points = $this->clampRedeemablePoints($frontendUser, $requestedPoints, $basketGoodsTotal);
        return new CreditPointsRedemption($points, $this->calculateRedemptionValue($points));
    }

    private function clampRedeemablePoints(int $frontendUser, int $requestedPoints, Money $basketGoodsTotal): int
    {
        $moneyPerPointCents = $this->getMoneyPerPoint()->getCents();
        if (!$this->isEnabled() || $requestedPoints <= 0 || $frontendUser === 0 || $moneyPerPointCents <= 0) {
            return 0;
        }
        $maxByBasketValue = intdiv($basketGoodsTotal->getCents(), $moneyPerPointCents);
        return max(0, min($requestedPoints, $this->getBalance($frontendUser), $maxByBasketValue));
    }
}
