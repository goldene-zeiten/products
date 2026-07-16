<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Express;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The answer to one express shipping-rate callback: the goods total and the shipping options available for
 * the wallet-supplied destination, each already carrying the resulting order total. An empty option list
 * means the destination cannot be served - the wallet shows it as unserviceable.
 */
#[Exclude]
final readonly class ExpressShippingQuote
{
    /**
     * @param ExpressShippingQuoteOption[] $options
     */
    public function __construct(
        private string $currency,
        private Money $goodsTotal,
        private array $options
    ) {}

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getGoodsTotal(): Money
    {
        return $this->goodsTotal;
    }

    /**
     * @return ExpressShippingQuoteOption[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'goodsTotal' => $this->goodsTotal->getCents(),
            'options' => array_map(
                static fn(ExpressShippingQuoteOption $option): array => $option->toArray(),
                $this->options
            ),
        ];
    }
}
