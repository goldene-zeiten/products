<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class BasketItem
{
    public function __construct(
        private int $productUid,
        private ?int $articleUid,
        private int $quantity
    ) {}

    public function getProductUid(): int
    {
        return $this->productUid;
    }

    public function getArticleUid(): ?int
    {
        return $this->articleUid;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
