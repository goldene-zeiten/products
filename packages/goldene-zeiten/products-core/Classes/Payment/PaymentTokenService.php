<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * Signs the callback URLs a payment gateway is sent back to. The token proves the URL was issued by this
 * shop for this order, so a stranger cannot walk order uids and trigger callbacks for someone else's
 * order.
 *
 * It does not prove the payment succeeded - only the gateway can say that, which is why the payment
 * method still has to verify the callback against the gateway itself.
 */
final class PaymentTokenService
{
    private const ADDITIONAL_SECRET = 'products-payment-callback';

    public function __construct(
        private readonly HashService $hashService
    ) {}

    public function generateToken(Order $order): string
    {
        return $this->hashService->hmac($this->subject($order), self::ADDITIONAL_SECRET);
    }

    public function isValid(Order $order, ?string $token): bool
    {
        return $token !== null && hash_equals($this->generateToken($order), $token);
    }

    private function subject(Order $order): string
    {
        return $order->getUid() . '-' . $order->getOrderNumber();
    }
}
