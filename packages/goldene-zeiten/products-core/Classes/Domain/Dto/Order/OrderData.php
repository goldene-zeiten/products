<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Order;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Read-only snapshot of an order - its totals, addresses and line items - passed to the order export and
 * refundable-payment integrator contracts. Built by {@see OrderDataFactory}.
 */
#[Exclude]
final readonly class OrderData
{
    /**
     * @param array<int, OrderItemData> $items
     * @param array<string, mixed> $taxBreakdown
     * @param array<int, mixed> $statusLog
     */
    public function __construct(
        public int $uid,
        public string $orderNumber,
        public ?\DateTimeImmutable $orderDate,
        public string $email,
        public ?OrderAddressData $billingAddress,
        public ?OrderAddressData $deliveryAddress,
        public string $paymentMethod,
        public PaymentStatus $paymentStatus,
        public OrderStatus $status,
        public string $invoiceNumber,
        public string $currency,
        public Money $totalNet,
        public Money $totalTax,
        public Money $totalGross,
        public Money $discountTotal,
        public Money $shippingTotal,
        public Money $handlingFeeTotal,
        public Money $depositTotal,
        public string $shippingProvider,
        public string $shippingOption,
        public string $shippingLabel,
        public string $taxCountry,
        public array $taxBreakdown,
        public array $statusLog,
        public string $customerNote,
        public string $giftMessage,
        public string $siteIdentifier,
        public array $items,
    ) {}
}
