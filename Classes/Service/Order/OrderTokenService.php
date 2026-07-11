<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use GoldeneZeiten\Products\Domain\Model\Order;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * Guest checkout has no login, so the order-detail page is secured by an HMAC token instead of a
 * frontend-user match - without it, a guest order's frontendUser is 0, same as an anonymous
 * visitor's, and the page would otherwise be reachable by any visitor guessing a sequential order
 * uid. Same shape as InvoiceTokenService/WithdrawalTokenService, distinct additional secret so the
 * token types stay non-interchangeable.
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
