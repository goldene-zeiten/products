<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Loyalty;

use GoldeneZeiten\Products\Core\Domain\Dto\Loyalty\LoyaltyContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\CheckoutAdjustment;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Domain\ValueObject\VatBuckets;
use GoldeneZeiten\Products\Core\Pricing\VatSplitter;
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
        iterable $providers,
        private readonly VatSplitter $vatSplitter
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
     * A redemption pays for part of the goods, so it is a price reduction: each one is split across the
     * goods' VAT rates and lowers net and VAT of each rate.
     *
     * @return CheckoutAdjustment[]
     */
    public function collectRedemption(LoyaltyContext $context): array
    {
        $goodsVatBuckets = VatBuckets::fromBasket($context->getBasketViewModel());
        $adjustments = [];
        foreach ($this->redemptions($context) as $redemption) {
            if ($redemption['adjustment'] !== null) {
                $adjustments = [...$adjustments, ...$this->vatSplitter->splitAdjustment($redemption['adjustment'], $goodsVatBuckets)];
            }
        }

        return $adjustments;
    }

    public function applyRedemption(Order $order, LoyaltyContext $context): void
    {
        foreach ($this->redemptions($context) as $redemption) {
            $redemption['provider']->applyRedemption($order, $redemption['context']);
        }
    }

    /**
     * Each programme redeems against what the programmes before it left of the goods, so together they
     * never pay for more than the goods cost. Quoting and booking walk the same chain, so each books
     * exactly what it quoted.
     *
     * @return list<array{provider: LoyaltyProviderInterface, context: LoyaltyContext, adjustment: ?CheckoutAdjustment}>
     */
    private function redemptions(LoyaltyContext $context): array
    {
        $redemptions = [];
        foreach ($this->providers as $provider) {
            $adjustment = $provider->quoteRedemption($context);
            $redemptions[] = [
                'provider' => $provider,
                'context' => $context,
                'adjustment' => $adjustment,
            ];
            if ($adjustment !== null) {
                $remaining = $context->getRemainingGoodsTotal()->add($adjustment->getAmount());
                $context = $context->withRemainingGoodsTotal(Money::fromCents(max(0, $remaining->getCents())));
            }
        }
        return $redemptions;
    }

    public function award(Order $order, LoyaltyContext $context): void
    {
        foreach ($this->providers as $provider) {
            $provider->award($order, $context);
        }
    }
}
