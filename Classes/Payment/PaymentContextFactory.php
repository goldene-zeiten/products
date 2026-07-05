<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Domain\Model\Order;

final class PaymentContextFactory
{
    public function createFromBasket(BasketViewModel $basketViewModel, Address $address, int $frontendUserUid): PaymentContext
    {
        return new PaymentContext(
            $basketViewModel->getTotalGross(),
            $basketViewModel->getCurrency(),
            $address->getCountry(),
            $frontendUserUid
        );
    }

    public function createFromOrder(Order $order): PaymentContext
    {
        return new PaymentContext(
            $order->getTotalGross(),
            $order->getCurrency(),
            $order->getTaxCountry(),
            $order->getFrontendUser()
        );
    }
}
