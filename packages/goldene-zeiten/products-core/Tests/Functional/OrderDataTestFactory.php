<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional;

use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderData;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;

/**
 * Builds a minimal {@see OrderData} for tests that only care about a field or two, without repeating the
 * DTO's full constructor at every call site.
 */
final class OrderDataTestFactory
{
    public static function minimal(string $orderNumber = 'ORD-42', string $invoiceNumber = '', string $paymentMethod = 'invoice'): OrderData
    {
        return new OrderData(
            uid: 1,
            orderNumber: $orderNumber,
            orderDate: null,
            email: '',
            billingAddress: null,
            deliveryAddress: null,
            paymentMethod: $paymentMethod,
            paymentStatus: PaymentStatus::OPEN,
            status: OrderStatus::NEW,
            invoiceNumber: $invoiceNumber,
            currency: 'EUR',
            totalNet: Money::fromCents(0),
            totalTax: Money::fromCents(0),
            totalGross: Money::fromCents(0),
            discountTotal: Money::fromCents(0),
            shippingTotal: Money::fromCents(0),
            handlingFeeTotal: Money::fromCents(0),
            depositTotal: Money::fromCents(0),
            shippingProvider: '',
            shippingOption: '',
            shippingLabel: '',
            taxCountry: '',
            taxBreakdown: [],
            statusLog: [],
            customerNote: '',
            giftMessage: '',
            siteIdentifier: '',
            items: [],
        );
    }
}
