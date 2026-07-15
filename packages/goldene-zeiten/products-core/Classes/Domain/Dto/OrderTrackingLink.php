<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class OrderTrackingLink
{
    public function __construct(
        private string $label,
        private string $url
    ) {}

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
