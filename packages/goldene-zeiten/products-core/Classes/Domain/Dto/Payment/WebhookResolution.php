<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Payment;

use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The outcome of a static (dashboard-configured) webhook: the order the verified payload resolved to, if
 * any, and the payment result to finalize it with. An unresolved payload could not be verified or did not
 * name a known order, so nothing is finalized and the gateway is told so. An ignored one was verified but is
 * an event the method does not act on; it is acknowledged so the gateway stops retrying it
 * {@see StaticWebhookPaymentMethodInterface}.
 */
#[Exclude]
final readonly class WebhookResolution
{
    private function __construct(
        private ?Order $order,
        private PaymentResult $result
    ) {}

    public static function resolved(Order $order, PaymentResult $result): self
    {
        return new self($order, $result);
    }

    public static function unresolved(PaymentResult $result): self
    {
        return new self(null, $result);
    }

    public static function ignored(): self
    {
        return new self(null, PaymentResult::unchanged());
    }

    public function isIgnored(): bool
    {
        return $this->order === null && $this->result->getState() === PaymentResultState::UNCHANGED;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function getResult(): PaymentResult
    {
        return $this->result;
    }
}
