<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Security;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\SecurityAspect;
use TYPO3\CMS\Core\Security\RequestToken;

/**
 * Verifies the request token a state-changing frontend form submits, so a cross-site POST cannot drive a
 * basket, wishlist or voucher action on a visitor's behalf.
 *
 * The token is issued by `<f:form requestToken="<scope>">` and validated by the core
 * {@see \TYPO3\CMS\Core\Middleware\RequestTokenMiddleware}, which exposes the outcome on the request's
 * {@see SecurityAspect} but does not itself reject anything for the frontend. This only asserts that a
 * token was received, verified, and carries the scope the action expects - the action redirects instead of
 * mutating when it does not.
 */
#[Autoconfigure(public: true)]
final class FrontendRequestTokenValidator
{
    public function __construct(
        private readonly Context $context
    ) {}

    public function isValid(string $scope): bool
    {
        $requestToken = SecurityAspect::provideIn($this->context)->getReceivedRequestToken();

        return $requestToken instanceof RequestToken && $requestToken->scope === $scope;
    }
}
