<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Invoice;

use GoldeneZeiten\Products\Domain\Model\Order;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * Guest checkout has no login, so the invoice download link is secured by an HMAC token bound to
 * the order instead - a guest can still re-fetch their invoice from the thank-you page/email
 * without an account, but can't guess another order's token from its uid alone.
 */
final class InvoiceTokenService
{
    private const ADDITIONAL_SECRET = 'products-invoice-download';

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
        return $order->getUid() . '-' . $order->getInvoiceNumber();
    }
}
