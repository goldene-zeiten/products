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

    public function isAvailable(PaymentContext $context): bool;

    /**
     * @return int fee in cents
     */
    public function calculateFee(PaymentContext $context): int;

    public function initiate(Order $order, PaymentContext $context): PaymentResult;
}
