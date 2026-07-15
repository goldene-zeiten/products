<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Domain\Dto;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A PayPal order as returned by "create order": its id and the URL the customer is redirected to in order
 * to approve the payment.
 */
#[Exclude]
final readonly class PaypalOrder
{
    public function __construct(
        public string $id,
        public string $approveUrl,
        public string $status,
    ) {}
}
