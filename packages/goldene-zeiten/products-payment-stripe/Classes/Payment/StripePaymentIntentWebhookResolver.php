<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Stripe\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\WebhookResolution;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Domain\Repository\PaymentTransactionRepository;
use GoldeneZeiten\Products\Payment\Stripe\Amount\StripeAmountConverter;
use Psr\Log\LoggerInterface;
use Stripe\Event;
use Stripe\StripeObject;

/**
 * Settles express orders from the PaymentIntent events of a verified Stripe webhook. An express payment has
 * no Checkout session, so the order is found by the PaymentIntent id the shop recorded when it accepted the
 * order, never by anything the event body claims about an order. A PaymentIntent the shop did not record
 * belongs to a Checkout session payment and is acknowledged without a change.
 *
 * Subscribe the webhook endpoint to `payment_intent.succeeded` and `payment_intent.payment_failed` as well
 * to let a payment that was still processing when the wallet sheet closed settle.
 */
final readonly class StripePaymentIntentWebhookResolver
{
    public const EVENT_SUCCEEDED = 'payment_intent.succeeded';
    public const EVENT_FAILED = 'payment_intent.payment_failed';

    public function __construct(
        private PaymentTransactionRepository $paymentTransactionRepository,
        private OrderRepository $orderRepository,
        private StripeAmountConverter $amountConverter,
        private LoggerInterface $logger
    ) {}

    public function supports(Event $event): bool
    {
        return $event->type === self::EVENT_SUCCEEDED || $event->type === self::EVENT_FAILED;
    }

    public function resolve(Event $event): WebhookResolution
    {
        $intent = $event->data->object;
        $intentId = (string)($intent->id ?? '');
        $transaction = $this->paymentTransactionRepository->findOneByExternalId(StripePaymentMethod::EXPRESS_PROVIDER_IDENTIFIER, $intentId);
        if ($transaction === null) {
            return WebhookResolution::ignored();
        }
        $order = $this->orderRepository->findByUid($transaction->getOrderUid());
        if ($order === null) {
            return WebhookResolution::unresolved(PaymentResult::failed('The Stripe webhook references an unknown order.', $intentId));
        }
        if ($event->type === self::EVENT_FAILED) {
            return WebhookResolution::resolved($order, PaymentResult::failed('The Stripe payment failed.', $intentId));
        }
        if (!$this->receivedOrderTotal($intent, $order)) {
            $this->logger->warning('Stripe PaymentIntent does not match the express order it is bound to.', [
                'order' => $order->getUid(),
                'paymentIntent' => $intentId,
            ]);
            return WebhookResolution::unresolved(PaymentResult::failed('The Stripe payment did not match the order.', $intentId));
        }

        return WebhookResolution::resolved($order, PaymentResult::completed(PaymentStatus::PAID, $intentId));
    }

    private function receivedOrderTotal(StripeObject $intent, Order $order): bool
    {
        $received = $intent->amount_received ?? $intent->amount ?? null;

        return $received !== null
            && (int)$received === $this->amountConverter->toMinorUnits($order->getTotalGross(), $order->getCurrency())
            && strtolower((string)($intent->currency ?? '')) === strtolower($order->getCurrency());
    }
}
