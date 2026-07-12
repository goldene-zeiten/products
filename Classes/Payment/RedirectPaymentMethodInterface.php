<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Model\Order;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A gateway may call back twice for the same payment (browser return + async webhook); both
 * methods must be idempotent, e.g. via {@see PaymentTransactionRepository::findOneByExternalId()}.
 */
interface RedirectPaymentMethodInterface extends PaymentMethodInterface
{
    public function handleReturn(ServerRequestInterface $request, Order $order): PaymentResult;

    public function handleWebhook(ServerRequestInterface $request, Order $order): PaymentResult;
}
