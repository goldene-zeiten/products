<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Checkout;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * A namespaced scratchpad each checkout feature keeps its own state in - the codes a discount collected,
 * a chosen option, whatever a feature needs to carry between requests. The core reads and writes it by
 * feature identifier without knowing what any feature stores, which is what lets a feature keep its
 * checkout state without the basket having to grow a field for it.
 */
final class CheckoutStateStore
{
    private const SESSION_KEY = 'tx_products_checkout_state';

    /**
     * @return array<string, mixed>
     */
    public function getPayload(ServerRequestInterface $request, string $providerIdentifier): array
    {
        $payload = $this->all($request)[$providerIdentifier] ?? [];

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(ServerRequestInterface $request, string $providerIdentifier, array $payload): void
    {
        $frontendUser = $this->frontendUser($request);
        if ($frontendUser === null) {
            return;
        }
        $state = $this->all($request);
        $state[$providerIdentifier] = $payload;
        $frontendUser->setKey('ses', self::SESSION_KEY, json_encode($state));
        $frontendUser->storeSessionData();
    }

    public function clear(ServerRequestInterface $request): void
    {
        $frontendUser = $this->frontendUser($request);
        if ($frontendUser === null) {
            return;
        }
        $frontendUser->setKey('ses', self::SESSION_KEY, null);
        $frontendUser->storeSessionData();
    }

    /**
     * @return array<string, mixed>
     */
    private function all(ServerRequestInterface $request): array
    {
        $frontendUser = $this->frontendUser($request);
        if ($frontendUser === null) {
            return [];
        }
        $decoded = json_decode((string)$frontendUser->getKey('ses', self::SESSION_KEY), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function frontendUser(ServerRequestInterface $request): ?FrontendUserAuthentication
    {
        $frontendUser = $request->getAttribute('frontend.user');

        return $frontendUser instanceof FrontendUserAuthentication ? $frontendUser : null;
    }
}
