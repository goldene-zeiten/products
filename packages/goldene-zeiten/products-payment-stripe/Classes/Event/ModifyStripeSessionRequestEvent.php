<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Stripe\Event;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Payment\Stripe\Configuration\StripeConfiguration;

/**
 * Fired just before a Stripe Checkout Session is created, so an integrator can adjust the outgoing
 * parameters - itemise the basket into several `line_items`, attach `metadata`, set a `locale` or
 * `payment_method_types`, and so on. The payload is the associative array passed to
 * `checkout.sessions.create()`.
 */
final class ModifyStripeSessionRequestEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private array $payload,
        private readonly Order $order,
        private readonly PaymentContext $context,
        private readonly StripeConfiguration $configuration,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getContext(): PaymentContext
    {
        return $this->context;
    }

    public function getConfiguration(): StripeConfiguration
    {
        return $this->configuration;
    }
}
