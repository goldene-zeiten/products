<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Security;

use GoldeneZeiten\Products\Core\Security\FrontendRequestTokenValidator;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\SecurityAspect;
use TYPO3\CMS\Core\Security\RequestToken;

/**
 * The validator is the single gate the basket, wishlist and voucher controllers use to turn away a
 * cross-site POST: it must accept only a received, verified token whose scope matches the action's.
 */
final class FrontendRequestTokenValidatorTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function acceptsAReceivedTokenWithTheExpectedScope(): void
    {
        $this->receiveRequestToken(RequestToken::create('products/basket'));

        $this->assertTrue($this->subject()->isValid('products/basket'));
    }

    #[Test]
    public function rejectsAReceivedTokenWithADifferentScope(): void
    {
        $this->receiveRequestToken(RequestToken::create('products/wishlist'));

        $this->assertFalse($this->subject()->isValid('products/basket'), 'A token for one plugin must not authorise another.');
    }

    #[Test]
    public function rejectsAnInvalidToken(): void
    {
        $this->receiveRequestToken(false);

        $this->assertFalse($this->subject()->isValid('products/basket'));
    }

    #[Test]
    public function rejectsAMissingToken(): void
    {
        $this->receiveRequestToken(null);

        $this->assertFalse($this->subject()->isValid('products/basket'));
    }

    private function subject(): FrontendRequestTokenValidator
    {
        return $this->get(FrontendRequestTokenValidator::class);
    }

    private function receiveRequestToken(RequestToken|false|null $token): void
    {
        SecurityAspect::provideIn($this->get(Context::class))->setReceivedRequestToken($token);
    }
}
