<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Withdrawal;

use GoldeneZeiten\Products\Domain\Model\Order;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * Guest checkout has no login, so the self-service withdrawal/cancellation link is secured by an
 * HMAC token bound to the order instead - the token itself acts as the "tracking code" a guest
 * needs alongside their email to withdraw an order, mirroring InvoiceTokenService's identical
 * guest-download-link approach (a distinct additional secret keeps the two token types from being
 * interchangeable even though they're bound to the same order subject).
 */
final class WithdrawalTokenService
{
    private const ADDITIONAL_SECRET = 'products-order-withdrawal';

    public function __construct(
        private readonly HashService $hashService
    ) {}

    public function generateToken(Order $order): string
    {
        return $this->hashService->hmac($this->subject($order), self::ADDITIONAL_SECRET);
    }

    public function isValid(Order $order, string $token): bool
    {
        return hash_equals($this->generateToken($order), $token);
    }

    private function subject(Order $order): string
    {
        return $order->getUid() . '-' . $order->getOrderNumber();
    }
}
