<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Checkout;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingSelection;
use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Everything the order factory needs beyond the basket itself. The money side is carried entirely by the
 * adjustment collection - the factory never asks for a shipping cost or a discount by name.
 */
#[Exclude]
final readonly class PlacementDetails
{
    public function __construct(
        private AdjustmentCollection $adjustments,
        private ?ShippingSelection $shippingSelection = null,
        private ?Address $deliveryAddress = null,
        private string $giftMessage = ''
    ) {}

    public function getAdjustments(): AdjustmentCollection
    {
        return $this->adjustments;
    }

    public function getShippingSelection(): ShippingSelection
    {
        return $this->shippingSelection ?? ShippingSelection::none();
    }

    public function getDeliveryAddress(): ?Address
    {
        return $this->deliveryAddress;
    }

    public function getGiftMessage(): string
    {
        return $this->giftMessage;
    }
}
