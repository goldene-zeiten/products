<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Model\Order;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('products.payment_method')]
interface PaymentMethodInterface
{
    public function getIdentifier(): string;

    public function getLabel(): string;

    /**
     * Discovery phase: may this method be offered for the given basket, country and customer?
     */
    public function isAvailable(PaymentContext $context): bool;

    /**
     * Higher priority is offered first. Methods sharing a priority keep their registration order.
     */
    public function getPriority(): int;

    /**
     * A surcharge for paying this way, added to the order total as a payment-fee adjustment.
     *
     * @return int fee in cents
     */
    public function calculateFee(PaymentContext $context): int;

    /**
     * Execution phase: start the payment for the order the customer selected this method for.
     */
    public function initiate(Order $order, PaymentContext $context): PaymentResult;
}
