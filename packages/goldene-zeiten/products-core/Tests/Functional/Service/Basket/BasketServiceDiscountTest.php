<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Basket;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * DI-wired pricing chain: PriceProviderInterface must be reached via getBasketViewModel().
 */
final class BasketServiceDiscountTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BasketServiceDiscountTest/basket_discount.csv');
    }

    #[Test]
    #[DataProvider('unitPriceProvider')]
    public function unitPriceReflectsDiscountConfiguration(int $frontendUserUid, int $expectedCents): void
    {
        $subject = $this->get(BasketService::class);
        $request = $this->requestFor($frontendUserUid);
        $subject->add($request, $this->product()->getUid() ?? 0, null, 1);

        $viewModel = $subject->getBasketViewModel($request);

        $this->assertSame($expectedCents, $viewModel->getItems()[0]->getUnitPriceGross()->getCents());
    }

    public static function unitPriceProvider(): \Generator
    {
        yield 'anonymous shopper pays undiscounted' => ['frontendUserUid' => 0, 'expectedCents' => 10000];
        yield 'discounted group pays reduced' => ['frontendUserUid' => 9, 'expectedCents' => 8000];
    }

    #[Test]
    #[DataProvider('isAlreadyDiscountedProvider')]
    public function isAlreadyDiscountedReflectsUserGroup(int $frontendUserUid, bool $expected): void
    {
        $subject = $this->get(BasketService::class);
        $request = $this->requestFor($frontendUserUid);
        $subject->add($request, $this->product()->getUid() ?? 0, null, 1);

        $this->assertSame($expected, $subject->isAlreadyDiscounted($request));
    }

    public static function isAlreadyDiscountedProvider(): \Generator
    {
        yield 'undiscounted shopper' => ['frontendUserUid' => 0, 'expected' => false];
        yield 'discounted group' => ['frontendUserUid' => 9, 'expected' => true];
    }

    private function product(): Product
    {
        $product = $this->get(ProductRepository::class)->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);
        return $product;
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
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser);
    }
}
