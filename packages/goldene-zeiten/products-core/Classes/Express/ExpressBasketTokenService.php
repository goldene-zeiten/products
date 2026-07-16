<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressBasket;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * Signs an {@see ExpressBasket} snapshot into a self-contained token and reads it back.
 *
 * The express shipping-rate callback and order creation run outside the session (the wallet sheet calls
 * them directly), so the basket travels with the request rather than being looked up. The signature is
 * what makes that safe: a customer cannot inflate quantities or drop the weight to cheat shipping, because
 * any edit to the payload breaks the HMAC and the token is rejected. It carries no session or identity -
 * only the basket the express button was rendered for.
 */
#[Autoconfigure(public: true)]
final class ExpressBasketTokenService
{
    private const ADDITIONAL_SECRET = 'products-express-basket';

    public function __construct(
        private readonly HashService $hashService
    ) {}

    public function issue(ExpressBasket $basket): string
    {
        $payload = $this->encode($basket->toArray());

        return $payload . '.' . $this->hashService->hmac($payload, self::ADDITIONAL_SECRET);
    }

    /**
     * The basket the token was issued for, or null if the token is missing, malformed or tampered with.
     */
    public function resolve(?string $token): ?ExpressBasket
    {
        if ($token === null || !str_contains($token, '.')) {
            return null;
        }
        [$payload, $signature] = explode('.', $token, 2);
        if (!hash_equals($this->hashService->hmac($payload, self::ADDITIONAL_SECRET), $signature)) {
            return null;
        }
        $data = json_decode($this->decode($payload), true);

        return is_array($data) ? ExpressBasket::fromArray($data) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        return rtrim(strtr(base64_encode(json_encode($data, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function decode(string $payload): string
    {
        return (string)base64_decode(strtr($payload, '-_', '+/'), true);
    }
}
