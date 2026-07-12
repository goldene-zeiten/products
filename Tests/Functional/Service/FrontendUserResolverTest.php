<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service;

use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/frontend_user_discounts.csv');
    }

    #[Test]
    #[DataProvider('discountPercentProvider')]
    public function getDiscountPercentResolvesTheApplicableDiscount(int $frontendUserUid, float $expectedPercent): void
    {
        $subject = $this->get(FrontendUserResolver::class);

        $this->assertSame($expectedPercent, $subject->getDiscountPercent($this->requestFor($frontendUserUid)));
    }

    public static function discountPercentProvider(): \Generator
    {
        yield 'anonymous visitor has no discount' => ['frontendUserUid' => 0, 'expectedPercent' => 0.0];
        yield 'user without any discount configured gets zero' => ['frontendUserUid' => 1, 'expectedPercent' => 0.0];
        yield 'user inherits their group\'s discount' => ['frontendUserUid' => 2, 'expectedPercent' => 10.0];
        yield 'personal discount applies without any group' => ['frontendUserUid' => 3, 'expectedPercent' => 15.0];
        // user 4: 20% personal vs. 10% from group 1 - personal wins as the higher rate.
        yield 'the best of personal and group discount wins rather than stacking' => ['frontendUserUid' => 4, 'expectedPercent' => 20.0];
        // user 5: group 1 = 10%, group 2 = 25% - the higher rate wins, they are never summed.
        yield 'the highest of multiple group discounts wins rather than stacking' => ['frontendUserUid' => 5, 'expectedPercent' => 25.0];
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
