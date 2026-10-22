<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Handoff;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * Signs the token of a checkout link: the basket it opens and the time it expires, so a customer cannot swap
 * the basket or extend the link. The token reads "basket.expiresAt.signature"; the basket token inside is
 * random and unguessable on its own, the signature additionally ties it to this installation and the expiry.
 */
#[Autoconfigure(public: true)]
final readonly class CheckoutLinkSigner
{
    private const ADDITIONAL_SECRET = 'products-mcp-checkout-link';

    public function __construct(
        private HashService $hashService
    ) {}

    public function issue(string $basketToken, int $expiresAt): string
    {
        $payload = $basketToken . '.' . $expiresAt;

        return $payload . '.' . $this->hashService->hmac($payload, self::ADDITIONAL_SECRET);
    }

    /**
     * The basket token a link opens, or null when the link is malformed, tampered with or expired at $now.
     */
    public function verify(string $linkToken, int $now): ?string
    {
        if (substr_count($linkToken, '.') !== 2) {
            return null;
        }
        [$basketToken, $expiresAt, $signature] = explode('.', $linkToken, 3);
        $payload = $basketToken . '.' . $expiresAt;
        if (!hash_equals($this->hashService->hmac($payload, self::ADDITIONAL_SECRET), $signature)) {
            return null;
        }

        return (int)$expiresAt > $now ? $basketToken : null;
    }
}
