<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class BasketItem
{
    /**
     * @param array<string, string> $selections option identifier => selected value
     */
    public function __construct(
        private int $productUid,
        private ?int $articleUid,
        private int $quantity,
        private array $selections = []
    ) {}

    /**
     * Identifies a set of option selections within the lines of one product and article; empty without
     * options.
     *
     * @param array<string, string> $selections
     */
    public static function optionsKeyOf(array $selections): string
    {
        if ($selections === []) {
            return '';
        }
        ksort($selections);
        return substr(hash('sha256', (string)json_encode($selections)), 0, 16);
    }

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

    /**
     * @return array<string, string>
     */
    public function getSelections(): array
    {
        return $this->selections;
    }

    public function getOptionsKey(): string
    {
        return self::optionsKeyOf($this->selections);
    }

    public function withQuantity(int $quantity): self
    {
        return new self($this->productUid, $this->articleUid, $quantity, $this->selections);
    }
}
