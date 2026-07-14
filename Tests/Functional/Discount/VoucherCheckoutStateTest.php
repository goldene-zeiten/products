<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Discount;

use GoldeneZeiten\Products\Discount\Voucher\VoucherCheckoutState;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class VoucherCheckoutStateTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    #[Test]
    public function addingACodeMakesItAvailableViaGetCodes(): void
    {
        $subject = $this->get(VoucherCheckoutState::class);
        $request = $this->sessionRequest();

        $subject->addCode($request, 'SUMMER20');

        $this->assertSame(['SUMMER20'], $subject->getCodes($request));
    }

    #[Test]
    public function addingSameCodeTwiceKeepsItOnce(): void
    {
        $subject = $this->get(VoucherCheckoutState::class);
        $request = $this->sessionRequest();

        $subject->addCode($request, 'SUMMER20');
        $subject->addCode($request, 'SUMMER20');

        $this->assertSame(['SUMMER20'], $subject->getCodes($request));
    }

    #[Test]
    public function removingACodeDropsIt(): void
    {
        $subject = $this->get(VoucherCheckoutState::class);
        $request = $this->sessionRequest();

        $subject->addCode($request, 'CODE1');
        $subject->addCode($request, 'CODE2');
        $subject->removeCode($request, 'CODE1');

        $this->assertSame(['CODE2'], $subject->getCodes($request));
    }

    #[Test]
    public function clearCodesEmptiesAllCodes(): void
    {
        $subject = $this->get(VoucherCheckoutState::class);
        $request = $this->sessionRequest();

        $subject->addCode($request, 'CODE1');
        $subject->addCode($request, 'CODE2');
        $subject->clearCodes($request);

        $this->assertSame([], $subject->getCodes($request));
    }

    #[Test]
    public function codesPersistedInSessionSurviveNewStateInstance(): void
    {
        $request = $this->sessionRequest();
        $firstInstance = $this->get(VoucherCheckoutState::class);

        $firstInstance->addCode($request, 'PERSIST123');

        // Get a new instance to verify session persistence
        $secondInstance = $this->get(VoucherCheckoutState::class);
        $this->assertSame(['PERSIST123'], $secondInstance->getCodes($request));
    }

    private function sessionRequest(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser);
    }
}
