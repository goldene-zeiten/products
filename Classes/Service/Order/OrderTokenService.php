<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * HMAC token secures guest orders (frontendUser = 0 for all guests).
 */
final class OrderTokenService
{
    private const ADDITIONAL_SECRET = 'products-order-detail';

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
