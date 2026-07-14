<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Loyalty;

use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What a loyalty programme needs to decide both of its directions: what a customer earns from an order,
 * and what they may spend on it. The request travels with it, because a programme's own configuration -
 * how much a point is worth, how points are earned - is the programme's business and not something the
 * core could put in the context for it.
 *
 * The remaining goods total is what is left after discounts, so points are spent against what the
 * customer actually still owes rather than the pre-discount total.
 */
#[Exclude]
final readonly class LoyaltyContext
{
    public function __construct(
        private ServerRequestInterface $request,
        private BasketViewModel $basketViewModel,
        private Money $remainingGoodsTotal,
        private int $frontendUserUid,
        private int $requestedSpendPoints = 0
    ) {}

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getBasketViewModel(): BasketViewModel
    {
        return $this->basketViewModel;
    }

    public function getRemainingGoodsTotal(): Money
    {
        return $this->remainingGoodsTotal;
    }

    public function getFrontendUserUid(): int
    {
        return $this->frontendUserUid;
    }

    public function getRequestedSpendPoints(): int
    {
        return $this->requestedSpendPoints;
    }
}
