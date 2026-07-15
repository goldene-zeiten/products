<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Event;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Payment\Klarna\Configuration\KlarnaConfiguration;

/**
 * Fired just before a Klarna payment session is created, so an integrator can adjust the outgoing payload -
 * itemise the basket into several `order_lines`, add tax, set a specific `locale`, attach
 * `merchant_data`, and so on. The payload is the associative array serialised to the Klarna Payments
 * create-session JSON.
 */
final class ModifyKlarnaSessionRequestEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private array $payload,
        private readonly Order $order,
        private readonly PaymentContext $context,
        private readonly KlarnaConfiguration $configuration,
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

    public function getConfiguration(): KlarnaConfiguration
    {
        return $this->configuration;
    }
}
