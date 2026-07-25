<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\WebhookResolution;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A gateway whose webhook is a single static endpoint configured in the gateway's own dashboard, so the
 * callback cannot carry the shop's per-order token the way a session-created callback URL can (Klarna,
 * Amazon Pay). Such a method verifies the gateway's own signature and resolves the order from the verified
 * payload itself, rather than from a URL parameter {@see PaymentWebhookMiddleware}.
 *
 * The signature must be verified before the payload's order reference is trusted: it is the only thing that
 * stops a forged body from naming an arbitrary order.
 */
interface StaticWebhookPaymentMethodInterface extends PaymentMethodInterface
{
    public function handleStaticWebhook(ServerRequestInterface $request): WebhookResolution;
}
