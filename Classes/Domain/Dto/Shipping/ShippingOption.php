<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Shipping;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * One shipping choice a carrier offers for a basket - "DHL Express", "Pickup point".
 *
 * A carrier offers several of these, which is what separates shipping from payment: the customer picks an
 * option, not a provider. The option is therefore identified by both, and the identifier a carrier uses
 * for its own options is its own business - the extension never interprets it.
 */
#[Exclude]
final readonly class ShippingOption
{
    private const KEY_SEPARATOR = ':';

    public function __construct(
        private string $providerIdentifier,
        private string $optionIdentifier,
        private string $label,
        private Money $cost,
        private ?float $taxRateOverride = null,
        private string $deliveryEstimate = ''
    ) {}

    /**
     * How the customer's choice is stored and resolved again: "tablerate:12", "dhl:express".
     */
    public function getKey(): string
    {
        return $this->providerIdentifier . self::KEY_SEPARATOR . $this->optionIdentifier;
    }

    /**
     * @return array{0: string, 1: string} provider identifier and option identifier
     */
    public static function splitKey(string $key): array
    {
        $parts = explode(self::KEY_SEPARATOR, $key, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    public function getProviderIdentifier(): string
    {
        return $this->providerIdentifier;
    }

    public function getOptionIdentifier(): string
    {
        return $this->optionIdentifier;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * What the carrier charges. Surcharges the shop adds on top - a bulky-goods surcharge, say - are not
     * part of this: they are the shop's, not the carrier's, and a free-shipping voucher waives the
     * carrier's rate without waiving them.
     */
    public function getCost(): Money
    {
        return $this->cost;
    }

    /**
     * A carrier may be taxed differently than the shop's default shipping tax rate. Null means the shop
     * decides.
     */
    public function getTaxRateOverride(): ?float
    {
        return $this->taxRateOverride;
    }

    public function getDeliveryEstimate(): string
    {
        return $this->deliveryEstimate;
    }
}
