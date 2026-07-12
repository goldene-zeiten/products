<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Basket;

use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Proves the DI-wired pricing chain end-to-end: PriceProviderInterface is aliased to
 * CategoryDiscountPriceProvider (see Services.yaml), which must actually be reached via
 * BasketService::getBasketViewModel() for a real FE-usergroup discount to show up in the basket.
 */
final class BasketServiceDiscountTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BasketServiceDiscountTest/basket_discount.csv');
        // TaxService reads Extbase settings eagerly in its constructor, which requires a request
        // resolvable via $GLOBALS['TYPO3_REQUEST'] outside a real dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
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
