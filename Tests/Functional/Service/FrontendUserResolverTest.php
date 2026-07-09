<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service;

use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class FrontendUserResolverTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private FrontendUserResolver $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/frontend_user_discounts.csv');
        $this->subject = $this->get(FrontendUserResolver::class);
    }

    #[Test]
    public function anonymousVisitorHasNoDiscount(): void
    {
        self::assertSame(0.0, $this->subject->getDiscountPercent($this->requestFor(0)));
    }

    #[Test]
    public function userWithoutAnyDiscountConfiguredGetsZero(): void
    {
        self::assertSame(0.0, $this->subject->getDiscountPercent($this->requestFor(1)));
    }

    #[Test]
    public function userInheritsTheirGroupsDiscount(): void
    {
        self::assertSame(10.0, $this->subject->getDiscountPercent($this->requestFor(2)));
    }

    #[Test]
    public function personalDiscountAppliesWithoutAnyGroup(): void
    {
        self::assertSame(15.0, $this->subject->getDiscountPercent($this->requestFor(3)));
    }

    #[Test]
    public function theBestOfPersonalAndGroupDiscountWinsRatherThanStacking(): void
    {
        // user 4: 20% personal vs. 10% from group 1 - personal wins as the higher rate.
        self::assertSame(20.0, $this->subject->getDiscountPercent($this->requestFor(4)));
    }

    #[Test]
    public function theHighestOfMultipleGroupDiscountsWinsRatherThanStacking(): void
    {
        // user 5: group 1 = 10%, group 2 = 25% - the higher rate wins, they are never summed.
        self::assertSame(25.0, $this->subject->getDiscountPercent($this->requestFor(5)));
    }

    private function requestFor(int $frontendUserUid): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        if ($frontendUserUid > 0) {
            $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('fe_users');
            $row = $queryBuilder->select('*')
                ->from('fe_users')
                ->where($queryBuilder->expr()->eq('uid', $frontendUserUid))
                ->executeQuery()
                ->fetchAssociative();
            $frontendUser->user = $row !== false ? $row : ['uid' => $frontendUserUid];
        }
        return (new ServerRequest('http://localhost/'))->withAttribute('frontend.user', $frontendUser);
    }
}
