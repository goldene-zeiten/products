<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Model\Order;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A gateway typically calls back twice for the same payment (the browser's synchronous return AND
 * an async webhook) - both handleReturn() and handleWebhook() implementations must be idempotent.
 * Check `PaymentTransactionRepository::findOneByExternalId($this->getIdentifier(), $externalId)`
 * before mutating anything: if it already returns a transaction in a terminal state, treat the
 * call as a no-op replay instead of re-processing the same payment.
 */
interface RedirectPaymentMethodInterface extends PaymentMethodInterface
{
    public function handleReturn(ServerRequestInterface $request, Order $order): PaymentResult;

    public function handleWebhook(ServerRequestInterface $request, Order $order): PaymentResult;
}
