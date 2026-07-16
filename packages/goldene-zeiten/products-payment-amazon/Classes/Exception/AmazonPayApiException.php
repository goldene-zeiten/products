<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Exception;

/**
 * Thrown when an Amazon Pay Checkout v2 API call cannot be completed: a transport failure, or a response
 * status that is neither success nor a recognised business outcome. The payment method catches it and
 * reports a failed payment result so the order stays unpaid rather than the checkout breaking.
 */
final class AmazonPayApiException extends \RuntimeException {}
