<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping;

use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingOption;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contract for shipping carriers. Shipping is shop-specific, so a carrier lives in its own extension and
 * plugs in here; the autoconfigure tag on this interface collects it with no further configuration.
 *
 * Who decides what:
 *
 * - The carrier decides what it can carry. Only it knows its own weight caps, its zones and the goods it
 *   refuses, so {@see ShippingProviderInterface::quote()} returns only options it can actually serve and
 *   an empty array when it can serve none. The extension never second-guesses that.
 * - The customer decides which option to use, from every carrier's options collected into one list.
 * - The shop decides which one is preselected, and what it charges on top of the carrier's rate.
 */
#[AutoconfigureTag('products.shipping_provider')]
interface ShippingProviderInterface
{
    public function getIdentifier(): string;

    /**
     * Higher priority is offered first. Carriers sharing a priority keep their registration order.
     */
    public function getPriority(): int;

    /**
     * Discovery phase: the options this carrier can serve for this basket, or none at all.
     *
     * @return ShippingOption[]
     */
    public function quote(ShippingContext $context): array;

    /**
     * Execution phase: the option the customer selected, re-quoted against the basket as it stands now.
     * Returns null when the option no longer applies - the basket may have changed since it was chosen.
     */
    public function resolve(string $optionIdentifier, ShippingContext $context): ?ShippingOption;
}
