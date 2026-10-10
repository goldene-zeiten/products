<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Enum;

/**
 * What a payment method reports back. COMPLETED and PENDING both mean the gateway took the payment on - money
 * is in, or on its way (SEPA, a fraud review) - so either accepts the order. FAILED means it never came
 * about. UNCHANGED reports nothing new: the payment is still in progress at the gateway, or the callback was
 * about something the method does not act on.
 */
enum PaymentResultState: string
{
    case COMPLETED = 'completed';
    case PENDING = 'pending';
    case REDIRECT_REQUIRED = 'redirect_required';
    case FAILED = 'failed';
    case UNCHANGED = 'unchanged';
}
