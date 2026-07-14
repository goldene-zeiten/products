<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Discount;

use GoldeneZeiten\Products\Domain\Dto\Discount\DiscountContext;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\ValueObject\CheckoutAdjustment;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Runs the registered discount providers in priority order: collecting what each one is worth, and later
 * booking each against the placed order.
 *
 * A provider runs against the adjustments the previous ones produced, so a discount can offset an earlier
 * one. With no provider registered the shop simply has no discounts, which is a valid shop.
 */
final class DiscountRegistry
{
    /**
     * @var DiscountProviderInterface[]
     */
    private array $providers;

    /**
     * @param iterable<DiscountProviderInterface> $providers
     */
    public function __construct(
        #[TaggedIterator('products.discount_provider')]
        iterable $providers
    ) {
        $this->providers = $this->sortByPriority([...$providers]);
    }

    /**
     * Each provider quotes against the adjustments accumulated so far - the ones handed in plus every
     * discount quoted before it - so a later discount can offset an earlier charge or discount.
     *
     * @return CheckoutAdjustment[] the discount adjustments, in the order they were produced
     */
    public function collect(DiscountContext $context): array
    {
        $accumulated = $context->getAccumulatedAdjustments();
        $collected = [];
        foreach ($this->providers as $provider) {
            $contextForProvider = new DiscountContext(
                $context->getGoodsTotal(),
                $context->getFrontendUserUid(),
                $context->getAppliedCodes(),
                $accumulated
            );
            foreach ($provider->quote($contextForProvider) as $adjustment) {
                $collected[] = $adjustment;
                $accumulated = $accumulated->with($adjustment);
            }
        }

        return $collected;
    }

    public function apply(Order $order, DiscountContext $context): void
    {
        foreach ($this->providers as $provider) {
            $provider->apply($order, $context);
        }
    }

    /**
     * @param DiscountProviderInterface[] $providers
     * @return DiscountProviderInterface[]
     */
    private function sortByPriority(array $providers): array
    {
        usort(
            $providers,
            static fn(DiscountProviderInterface $a, DiscountProviderInterface $b): int => $b->getPriority() <=> $a->getPriority()
        );

        return $providers;
    }
}
