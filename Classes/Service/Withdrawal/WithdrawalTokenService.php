<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Withdrawal;

use GoldeneZeiten\Products\Domain\Model\Order;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * Secures guest withdrawal via HMAC token bound to order.
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
