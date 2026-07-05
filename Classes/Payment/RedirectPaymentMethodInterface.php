<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Model\Order;
use Psr\Http\Message\ServerRequestInterface;

interface RedirectPaymentMethodInterface extends PaymentMethodInterface
{
    public function handleReturn(ServerRequestInterface $request, Order $order): PaymentResult;

    public function handleWebhook(ServerRequestInterface $request, Order $order): PaymentResult;
}
