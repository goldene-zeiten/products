<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Presents an express-checkout provider as the payment method an order records it was paid with, so
 * {@see ExpressOrderService} can reuse the normal order creation without a second payment-method seam.
 *
 * It carries only the provider's identity - an express payment is already settled by the wallet before the
 * order exists, so there is nothing to initiate and no surcharge to add. It is never registered as a real
 * payment method and its execution methods are never reached.
 */
#[Exclude]
final readonly class ExpressPaymentDescriptor implements PaymentMethodInterface
{
    public function __construct(
        private ExpressCheckoutProviderInterface $provider
    ) {}

    public function getIdentifier(): string
    {
        return $this->provider->getIdentifier();
    }

    public function getLabel(): string
    {
        return $this->provider->getLabel();
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
        return PaymentResult::completed(PaymentStatus::PAID);
    }
}
