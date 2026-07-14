<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping;

use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Domain\Model\ShippingMethod;
use GoldeneZeiten\Products\Domain\Repository\ShippingMethodRepository;

/**
 * The carrier every shop has without installing anything: the shipping methods maintained as records in
 * the backend, matched against the basket's weight, value and destination country.
 *
 * Its option identifiers happen to be the uids of those records, which is its own business - the
 * extension only ever sees "tablerate:12". It ships as the default the same way invoice payment does, so
 * a shop works out of the box, and sorts below any carrier an integrator registers.
 */
final class TableRateShippingProvider implements ShippingProviderInterface
{
    public const IDENTIFIER = 'tablerate';

    public function __construct(
        private readonly ShippingMethodRepository $shippingMethodRepository
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getPriority(): int
    {
        return 0;
    }

    /**
     * @return ShippingOption[]
     */
    public function quote(ShippingContext $context): array
    {
        $candidates = $this->shippingMethodRepository->findApplicableForCountry($context->getCountryCode());
        $applicable = array_filter(
            $candidates,
            static fn(ShippingMethod $method): bool => $method->isApplicable($context->getTotalWeight(), $context->getGoodsTotal())
        );

        return array_values(array_map($this->toOption(...), $applicable));
    }

    public function resolve(string $optionIdentifier, ShippingContext $context): ?ShippingOption
    {
        foreach ($this->quote($context) as $option) {
            if ($option->getOptionIdentifier() === $optionIdentifier) {
                return $option;
            }
        }

        return null;
    }

    private function toOption(ShippingMethod $method): ShippingOption
    {
        return new ShippingOption(
            self::IDENTIFIER,
            (string)($method->getUid() ?? 0),
            $method->getTitle(),
            $method->getRate(),
            $method->getEffectiveTaxRateOverride()
        );
    }
}
