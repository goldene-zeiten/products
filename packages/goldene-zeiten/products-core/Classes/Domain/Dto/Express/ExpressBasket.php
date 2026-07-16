<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContextItem;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A snapshot of the shipping-relevant parts of a basket, small enough to travel inside a signed token.
 *
 * The express shipping-rate callback runs without a session cookie - the wallet sheet calls it directly -
 * so the basket cannot be read from the session. Instead this snapshot is signed into a token when the
 * express button is rendered {@see ExpressBasketTokenService} and handed back on every callback; the
 * destination country and postcode come from the wallet, everything else from here.
 */
#[Exclude]
final readonly class ExpressBasket
{
    /**
     * @param ShippingContextItem[] $items
     */
    public function __construct(
        private array $items,
        private int $totalWeight,
        private Money $totalGross,
        private string $currency,
        private int $frontendUserUid = 0
    ) {}

    public function getTotalGross(): Money
    {
        return $this->totalGross;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * The carrier's view of this basket for a given destination - the missing half the wallet supplies.
     */
    public function toShippingContext(string $countryCode, string $postCode): ShippingContext
    {
        return new ShippingContext(
            $this->items,
            $this->totalWeight,
            $this->totalGross,
            $this->currency,
            $countryCode,
            $postCode,
            $this->frontendUserUid
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                static fn(ShippingContextItem $item): array => [
                    'quantity' => $item->getQuantity(),
                    'weight' => $item->getWeight(),
                    'bulky' => $item->isBulky(),
                    'shippingClass' => $item->getShippingClass(),
                ],
                $this->items
            ),
            'totalWeight' => $this->totalWeight,
            'totalGross' => $this->totalGross->getCents(),
            'currency' => $this->currency,
            'frontendUserUid' => $this->frontendUserUid,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];
        $items = array_map(
            static fn(array $item): ShippingContextItem => new ShippingContextItem(
                (int)($item['quantity'] ?? 0),
                (int)($item['weight'] ?? 0),
                (bool)($item['bulky'] ?? false),
                (string)($item['shippingClass'] ?? '')
            ),
            $rawItems
        );

        return new self(
            $items,
            (int)($data['totalWeight'] ?? 0),
            Money::fromCents((int)($data['totalGross'] ?? 0)),
            (string)($data['currency'] ?? ''),
            (int)($data['frontendUserUid'] ?? 0)
        );
    }
}
