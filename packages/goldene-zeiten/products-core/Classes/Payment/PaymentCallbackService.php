<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Payment\Exception\PaymentCallbackException;
use GoldeneZeiten\Products\Core\Payment\Exception\PaymentMethodNotFoundException;
use GoldeneZeiten\Products\Core\Service\Order\OrderFinalizationService;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatches what a payment gateway sends back to the method that started the payment: the customer
 * returning from the gateway, and the gateway's own asynchronous confirmation.
 *
 * Both are untrusted. This service only proves the callback belongs to an order of this shop - whether
 * the payment actually succeeded is decided by the payment method, which is expected to verify the
 * callback against the gateway rather than believe what the caller claims.
 *
 * Both are also replayable: a customer can reload the return page and a gateway can retry its webhook,
 * so finalization is idempotent {@see OrderFinalizationService::finalize()}.
 */
final class PaymentCallbackService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly PaymentMethodRegistry $paymentMethodRegistry,
        private readonly PaymentTokenService $paymentTokenService,
        private readonly OrderFinalizationService $orderFinalizationService
    ) {}

    public function handleReturn(int $orderUid, string $token, ServerRequestInterface $request): PaymentResult
    {
        $order = $this->resolveOrder($orderUid, $token);
        $paymentResult = $this->redirectMethodFor($order)->handleReturn($request, $order);

        // A multi-hop gateway (Amazon Pay) returns the customer once to settle the amount, then needs a
        // second redirect back to itself before the payment exists; finalizing on that first return would
        // confirm an unpaid order. Only a settled - or failed - result finalizes {@see PaymentReturnMiddleware}.
        if ($paymentResult->getState() !== PaymentResultState::REDIRECT_REQUIRED) {
            $this->orderFinalizationService->finalize($order, $paymentResult, $request);
        }

        return $paymentResult;
    }

    public function handleWebhook(int $orderUid, string $token, ServerRequestInterface $request): PaymentResult
    {
        $order = $this->resolveOrder($orderUid, $token);
        $paymentResult = $this->redirectMethodFor($order)->handleWebhook($request, $order);
        $this->orderFinalizationService->finalize($order, $paymentResult, $request);

        return $paymentResult;
    }

    /**
     * The order is resolved from the signed token alone, never from a session: a webhook arrives without
     * one, and the customer may return in a different browser than they left in.
     */
    public function resolveOrder(int $orderUid, string $token): Order
    {
        $order = $orderUid > 0 ? $this->orderRepository->findByUid($orderUid) : null;
        if (!$order instanceof Order || !$this->paymentTokenService->isValid($order, $token)) {
            throw new PaymentCallbackException(
                sprintf('No order matches payment callback for uid %d.', $orderUid),
                1784073610
            );
        }

        return $order;
    }

    private function redirectMethodFor(Order $order): RedirectPaymentMethodInterface
    {
        try {
            $paymentMethod = $this->paymentMethodRegistry->get($order->getPaymentMethod());
        } catch (PaymentMethodNotFoundException $exception) {
            throw new PaymentCallbackException(
                sprintf('Payment method "%s" of order %d is no longer registered.', $order->getPaymentMethod(), $order->getUid() ?? 0),
                1784073611,
                $exception
            );
        }
        if (!$paymentMethod instanceof RedirectPaymentMethodInterface) {
            throw new PaymentCallbackException(
                sprintf('Payment method "%s" does not handle callbacks.', $paymentMethod->getIdentifier()),
                1784073612
            );
        }

        return $paymentMethod;
    }
}
