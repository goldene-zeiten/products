<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Loyalty;

use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What a loyalty programme needs to decide both of its directions: what a customer earns from an order,
 * and what they may spend on it. The request travels with it, because a programme's own configuration -
 * how much a point is worth, how points are earned - is the programme's business and not something the
 * core could put in the context for it.
 *
 * The remaining goods total is what is left of the goods after discounts - and, for a programme redeeming
 * after another, after that programme's redemption - so points are spent against what the customer still
 * owes for the goods. A waived shipping cost does not lower it. When awarding, it is what the goods cost
 * the customer after every discount and redemption.
 */
#[Exclude]
final readonly class LoyaltyContext
{
    public function __construct(
        private ServerRequestInterface $request,
        private BasketViewModel $basketViewModel,
        private Money $remainingGoodsTotal,
        private int $frontendUserUid
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

    public function withRemainingGoodsTotal(Money $remainingGoodsTotal): self
    {
        return new self($this->request, $this->basketViewModel, $remainingGoodsTotal, $this->frontendUserUid);
    }

    public function getFrontendUserUid(): int
    {
        return $this->frontendUserUid;
    }

}
