<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Checkout;

use GoldeneZeiten\Products\Domain\Model\Order;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class OrderPlacementResult
{
    private function __construct(
        private Order $order,
        private string $redirectUrl = ''
    ) {}

    public static function forOrder(Order $order): self
    {
        return new self($order);
    }

    public static function forRedirect(Order $order, string $redirectUrl): self
    {
        return new self($order, $redirectUrl);
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function requiresRedirect(): bool
    {
        return $this->redirectUrl !== '';
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }
}
