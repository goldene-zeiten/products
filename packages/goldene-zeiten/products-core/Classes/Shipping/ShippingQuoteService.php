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
     * An empty key means the customer has not chosen yet, which is not an error - shipping may be
     * disabled, or the basket may not have reached the shipping step. A key that no longer resolves is an
     * error: the basket changed under the customer and the option they picked cannot be honoured.
     */
    public function resolveSelection(
        ProductsConfiguration $configuration,
        ShippingContext $context,
        string $optionKey,
        ?ServerRequestInterface $request = null
    ): ShippingSelection {
        if (!$configuration->isShippingEnabled() || $optionKey === '') {
            return ShippingSelection::none();
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
