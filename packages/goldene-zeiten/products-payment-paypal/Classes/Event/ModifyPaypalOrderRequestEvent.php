<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Event;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Payment\Paypal\Configuration\PaypalConfiguration;

/**
 * Fired just before a "create order" request is sent to PayPal, so an integrator can adjust the outgoing
 * payload - itemise the basket into `purchase_units[].items`, add a shipping address, set `soft_descriptor`
 * or `invoice_id`, switch the experience context, and so on. The payload is the associative array
 * serialised to the PayPal Orders v2 create-order JSON.
 */
final class ModifyPaypalOrderRequestEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private array $payload,
        private readonly Order $order,
        private readonly PaymentContext $context,
        private readonly PaypalConfiguration $configuration,
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

    public function getConfiguration(): PaypalConfiguration
    {
        return $this->configuration;
    }
}
