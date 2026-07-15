<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Discount;

use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Everything a discount provider may base a discount on: the goods total it applies to, the customer, the
 * adjustments accumulated so far, and the request - so a provider that keys off something the customer
 * entered (a voucher code, say) reads it from the request itself and the core stays unaware of it.
 *
 * The accumulated adjustments are what let a discount offset an earlier charge without knowing where it
 * came from - a free-shipping discount negates the shipping adjustment it finds in here rather than
 * calling into shipping. Discounts therefore run after the charges they may offset.
 */
#[Exclude]
final readonly class DiscountContext
{
    public function __construct(
        private Money $goodsTotal,
        private int $frontendUserUid,
        private ServerRequestInterface $request,
        private AdjustmentCollection $accumulatedAdjustments
    ) {}

    public function getGoodsTotal(): Money
    {
        return $this->goodsTotal;
    }

    public function getFrontendUserUid(): int
    {
        return $this->frontendUserUid;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getAccumulatedAdjustments(): AdjustmentCollection
    {
        return $this->accumulatedAdjustments;
    }
}
