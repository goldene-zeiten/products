<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use Psr\Http\Message\ServerRequestInterface;

final class PaymentContextFactory
{
    public function __construct(
        private readonly PaymentUrlFactory $paymentUrlFactory
    ) {}

    /**
     * For the discovery phase, where no order exists yet: a method decides whether it may be offered and
     * what it would charge. There is nothing to hand a gateway yet, so the callback URLs stay empty.
     */
    public function createFromBasket(BasketViewModel $basketViewModel, Address $address, int $frontendUserUid): PaymentContext
    {
        return new PaymentContext(
            $basketViewModel->getTotalGross(),
            $basketViewModel->getCurrency(),
            $address->getCountry(),
            $frontendUserUid
        );
    }

    /**
     * For the execution phase: the order exists, so a redirect method can be told where to send the
     * customer back to and where to post its confirmation.
     */
    public function createFromOrder(Order $order, ServerRequestInterface $request): PaymentContext
    {
        $callbackUrls = $this->paymentUrlFactory->createFor($order, $request);

        return new PaymentContext(
            $order->getTotalGross(),
            $order->getCurrency(),
            $order->getTaxCountry(),
            $order->getFrontendUser(),
            $callbackUrls->getReturnUrl(),
            $callbackUrls->getCancelUrl(),
            $callbackUrls->getWebhookUrl()
        );
    }
}
