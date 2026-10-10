<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Shipping;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingSelection;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Core\Service\TaxService;
use GoldeneZeiten\Products\Core\Shipping\Exception\MissingShippingOptionException;
use GoldeneZeiten\Products\Core\Shipping\Exception\NoShippingOptionAvailableException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Turns what the carriers offer into what the customer pays. The carriers quote their own rates; every
 * charge on top of them is the shop's own policy and belongs here rather than in any carrier: the
 * frontend-usergroup discount, the bulky-goods surcharge and the shipping tax rate.
 */
final class ShippingQuoteService
{
    public function __construct(
        private readonly ShippingProviderRegistry $registry,
        private readonly TaxService $taxService,
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    /**
     * @return ShippingOption[]
     */
    public function getAvailableOptions(ProductsConfiguration $configuration, ShippingContext $context): array
    {
        if (!$configuration->isShippingEnabled()) {
            return [];
        }

        return $this->registry->getAvailableOptions($context);
    }

    /**
     * Whether the customer may choose this option for the basket right now.
     */
    public function isOffered(ProductsConfiguration $configuration, ShippingContext $context, string $optionKey): bool
    {
        return $configuration->isShippingEnabled() && $optionKey !== '' && $this->registry->isOffered($optionKey, $context);
    }

    /**
     * While shipping is disabled, nothing is charged. While it is enabled, a choice is required: an empty
     * key means the customer skipped the shipping step, and a key that no longer resolves means the basket
     * or address changed under the customer - both must not ship for free.
     *
     * @throws MissingShippingOptionException
     * @throws NoShippingOptionAvailableException
     */
    public function resolveSelection(
        ProductsConfiguration $configuration,
        ShippingContext $context,
        string $optionKey,
        ?ServerRequestInterface $request = null
    ): ShippingSelection {
        if (!$configuration->isShippingEnabled()) {
            return ShippingSelection::none();
        }
        if ($optionKey === '') {
            throw new MissingShippingOptionException('No shipping option was chosen for this basket.', 1791662975);
        }

        $option = $this->registry->resolveOption($optionKey, $context);
        if ($option === null) {
            throw new NoShippingOptionAvailableException(
                sprintf('Shipping option "%s" is not available for this basket and country "%s".', $optionKey, $context->getCountryCode()),
                1784073620
            );
        }

        return new ShippingSelection(
            $option,
            $this->applyDiscount($option->getCost(), $request),
            $this->calculateBulkySurcharge($configuration, $context),
            $this->taxService->getShippingTaxRate($configuration, $option->getTaxRateOverride(), $context->getCountryCode())
        );
    }

    private function applyDiscount(Money $amount, ?ServerRequestInterface $request): Money
    {
        if ($request === null) {
            return $amount;
        }

        return $amount->discountByPercent($this->frontendUserResolver->getDiscountPercent($request));
    }

    /**
     * An oversized item costs the shop extra to handle no matter which carrier takes it, so this is not a
     * carrier's charge and a free-shipping voucher does not waive it.
     */
    private function calculateBulkySurcharge(ProductsConfiguration $configuration, ShippingContext $context): Money
    {
        $surchargePerUnit = $configuration->getBulkySurcharge();
        if ($surchargePerUnit->getCents() === 0) {
            return Money::fromCents(0);
        }

        $bulkyUnits = 0;
        foreach ($context->getItems() as $item) {
            if ($item->isBulky()) {
                $bulkyUnits += $item->getQuantity();
            }
        }

        return $surchargePerUnit->multiply($bulkyUnits);
    }
}
