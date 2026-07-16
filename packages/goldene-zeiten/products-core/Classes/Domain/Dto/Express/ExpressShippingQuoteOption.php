<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Express;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * One shipping option as an express wallet sheet needs it: the carrier cost to display next to the option,
 * and the order total that selecting it produces. The wallet-specific JSON shaping (Apple/Google/Stripe
 * each differ) is the express provider's job, not core's - this is the wallet-agnostic middle.
 */
#[Exclude]
final readonly class ExpressShippingQuoteOption
{
    public function __construct(
        private string $key,
        private string $label,
        private Money $shippingAmount,
        private Money $orderTotal,
        private string $deliveryEstimate = ''
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getShippingAmount(): Money
    {
        return $this->shippingAmount;
    }

    public function getOrderTotal(): Money
    {
        return $this->orderTotal;
    }

    public function getDeliveryEstimate(): string
    {
        return $this->deliveryEstimate;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'shippingAmount' => $this->shippingAmount->getCents(),
            'orderTotal' => $this->orderTotal->getCents(),
            'deliveryEstimate' => $this->deliveryEstimate,
        ];
    }
}
