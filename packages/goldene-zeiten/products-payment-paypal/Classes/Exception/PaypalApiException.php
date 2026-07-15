<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Exception;

/**
 * Thrown when a PayPal Orders/Payments API call cannot be completed: a transport failure, or a response
 * status that is neither success nor a recognised business outcome. The payment method catches it and
 * reports a failed {@see \GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult} so the order stays
 * unpaid rather than the checkout breaking.
 */
final class PaypalApiException extends \RuntimeException {}
