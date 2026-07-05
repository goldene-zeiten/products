<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use Psr\Http\Message\ServerRequestInterface;

final class BeforeOrderPlacedEvent
{
    private bool $vetoed = false;
    private string $vetoReason = '';

    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly BasketViewModel $basketViewModel,
        private readonly Address $address,
        private readonly PaymentMethodInterface $paymentMethod
    ) {}

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getBasketViewModel(): BasketViewModel
    {
        return $this->basketViewModel;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getPaymentMethod(): PaymentMethodInterface
    {
        return $this->paymentMethod;
    }

    public function veto(string $reason): void
    {
        $this->vetoed = true;
        $this->vetoReason = $reason;
    }

    public function isVetoed(): bool
    {
        return $this->vetoed;
    }

    public function getVetoReason(): string
    {
        return $this->vetoReason;
    }
}
