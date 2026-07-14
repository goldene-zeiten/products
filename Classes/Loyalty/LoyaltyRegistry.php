<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Loyalty;

use GoldeneZeiten\Products\Domain\Dto\Loyalty\LoyaltyContext;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\ValueObject\CheckoutAdjustment;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Serves the registered loyalty programmes across both directions of a checkout: showing a balance,
 * refusing an unaffordable spend, booking the spend, and awarding what the order earned. With none
 * registered the shop simply has no loyalty, which is a valid shop.
 */
final class LoyaltyRegistry
{
    /**
     * @var LoyaltyProviderInterface[]
     */
    private array $providers;

    /**
     * @param iterable<LoyaltyProviderInterface> $providers
     */
    public function __construct(
        #[TaggedIterator('products.loyalty_provider')]
        iterable $providers
    ) {
        $this->providers = [...$providers];
    }

    public function getBalance(LoyaltyContext $context): int
    {
        $balance = 0;
        foreach ($this->providers as $provider) {
            $balance += $provider->getBalance($context);
        }

        return $balance;
    }

    public function assertRedeemable(LoyaltyContext $context): void
    {
        foreach ($this->providers as $provider) {
            $provider->assertRedeemable($context);
        }
    }

    /**
     * @return CheckoutAdjustment[]
     */
    public function collectRedemption(LoyaltyContext $context): array
    {
        $adjustments = [];
        foreach ($this->providers as $provider) {
            $adjustment = $provider->quoteRedemption($context);
            if ($adjustment !== null) {
                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }

    public function applyRedemption(Order $order, LoyaltyContext $context): void
    {
        foreach ($this->providers as $provider) {
            $provider->applyRedemption($order, $context);
        }
    }

    public function award(Order $order, LoyaltyContext $context): void
    {
        foreach ($this->providers as $provider) {
            $provider->award($order, $context);
        }
    }
}
