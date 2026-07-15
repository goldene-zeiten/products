<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\LoyaltyFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Loyalty\LoyaltyContext;
use GoldeneZeiten\Products\Core\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\CheckoutAdjustment;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Loyalty\LoyaltyProviderInterface;

/**
 * Fixture loyalty provider with a deterministic, in-memory balance keyed by frontend user.
 * Proves an EXTERNAL loyalty provider reaches the order total through the contract, and
 * that it handles both spending and earning directions.
 *
 * All balances are stored in a public static array to remain self-contained (no database).
 * Tests seed balances via the public static property and reset it in setUp()/tearDown().
 */
final class FixtureLoyaltyProvider implements LoyaltyProviderInterface
{
    /**
     * @var array<int, int> Balances keyed by frontend user UID, in points
     */
    public static array $balances = [];

    public function getIdentifier(): string
    {
        return 'fixture-loyalty';
    }

    public function getBalance(LoyaltyContext $context): int
    {
        return self::$balances[$context->getFrontendUserUid()] ?? 0;
    }

    public function assertRedeemable(LoyaltyContext $context): void
    {
        $requestedPoints = $this->requestedPoints($context);
        if ($requestedPoints <= 0) {
            return;
        }

        $balance = $this->getBalance($context);
        if ($requestedPoints > $balance) {
            throw new FixtureLoyaltyException(
                sprintf('Requested %d loyalty points but balance is only %d.', $requestedPoints, $balance),
                1784073640
            );
        }
    }

    public function quoteRedemption(LoyaltyContext $context): ?CheckoutAdjustment
    {
        $requestedPoints = $this->requestedPoints($context);
        if ($requestedPoints <= 0) {
            return null;
        }

        return new CheckoutAdjustment(
            AdjustmentType::LOYALTY,
            'fixture-loyalty',
            '',
            Money::fromCents(-$requestedPoints),
            0.0,
            ['points' => (string)$requestedPoints]
        );
    }

    public function applyRedemption(Order $order, LoyaltyContext $context): void
    {
        $requestedPoints = $this->requestedPoints($context);
        if ($requestedPoints <= 0) {
            return;
        }

        $uid = $context->getFrontendUserUid();
        $currentBalance = self::$balances[$uid] ?? 0;
        self::$balances[$uid] = max(0, $currentBalance - $requestedPoints);
    }

    public function award(Order $order, LoyaltyContext $context): void
    {
        $uid = $context->getFrontendUserUid();
        $currentBalance = self::$balances[$uid] ?? 0;
        self::$balances[$uid] = $currentBalance + 5;
    }

    /**
     * Reset all balances. Called from test setUp()/tearDown().
     */
    public static function reset(): void
    {
        self::$balances = [];
    }

    private function requestedPoints(LoyaltyContext $context): int
    {
        $body = $context->getRequest()->getParsedBody();

        return is_array($body) ? (int)($body['spendPoints'] ?? 0) : 0;
    }
}
