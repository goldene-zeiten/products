<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PaymentFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\RedirectPaymentMethodInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A fixture gateway that returns the customer twice, like Amazon Pay: the first return still needs a
 * redirect back to the gateway (so it must not finalize), the second settles the payment. The leg is
 * distinguished by a `leg=second` query parameter the fixture appends to its own second return URL.
 */
final class TwoHopRedirectPaymentMethod implements RedirectPaymentMethodInterface
{
    public function getIdentifier(): string
    {
        return 'fixture-twohop';
    }

    public function getLabel(): string
    {
        return 'Two-Hop Redirect Fixture Payment';
    }

    public function isAvailable(PaymentContext $context): bool
    {
        return true;
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function calculateFee(PaymentContext $context): int
    {
        return 0;
    }

    public function initiate(Order $order, PaymentContext $context): PaymentResult
    {
        return PaymentResult::redirectRequired('https://gateway.example/first-hop', 'FIXTURE-TWOHOP');
    }

    public function handleReturn(ServerRequestInterface $request, Order $order): PaymentResult
    {
        if (($request->getQueryParams()['leg'] ?? '') === 'second') {
            return PaymentResult::completed(PaymentStatus::PAID, 'FIXTURE-TWOHOP-DONE');
        }

        return PaymentResult::redirectRequired('https://gateway.example/second-hop', 'FIXTURE-TWOHOP');
    }

    public function handleWebhook(ServerRequestInterface $request, Order $order): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::PAID, 'FIXTURE-TWOHOP-WEBHOOK');
    }
}
