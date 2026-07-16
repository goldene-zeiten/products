<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Event;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Payment\Amazon\Configuration\AmazonPayConfiguration;

/**
 * Dispatched just before the Amazon Pay Create Checkout Session request is sent, so an integrator can
 * adjust the payload - add merchant metadata, a note to the buyer, or a recurring charge permission -
 * without replacing the payment method.
 */
final class ModifyAmazonCheckoutSessionRequestEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private array $payload,
        public readonly Order $order,
        public readonly PaymentContext $context,
        public readonly AmazonPayConfiguration $configuration,
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
}
