<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Basket;

use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
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

    private BasketService $basketService;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/basket_discount.csv');
        // TaxService reads Extbase settings eagerly in its constructor, which requires a request
        // resolvable via $GLOBALS['TYPO3_REQUEST'] outside a real dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $this->basketService = $this->get(BasketService::class);
        $product = $this->get(ProductRepository::class)->findByUid(1);
        self::assertInstanceOf(Product::class, $product);
        $this->product = $product;
    }

    #[Test]
    public function anAnonymousShopperPaysTheUndiscountedPrice(): void
    {
        $request = $this->requestFor(0);
        $this->basketService->add($request, $this->product->getUid() ?? 0, null, 1);

        $viewModel = $this->basketService->getBasketViewModel($request);

        self::assertSame(10000, $viewModel->getItems()[0]->getUnitPriceGross()->getCents());
    }

    #[Test]
    public function aDiscountedGroupsShopperPaysTheReducedPrice(): void
    {
        $request = $this->requestFor(9);
        $this->basketService->add($request, $this->product->getUid() ?? 0, null, 1);

        $viewModel = $this->basketService->getBasketViewModel($request);

        self::assertSame(8000, $viewModel->getItems()[0]->getUnitPriceGross()->getCents());
    }

    #[Test]
    public function isAlreadyDiscountedIsFalseForAnUndiscountedShopper(): void
    {
        $request = $this->requestFor(0);
        $this->basketService->add($request, $this->product->getUid() ?? 0, null, 1);

        self::assertFalse($this->basketService->isAlreadyDiscounted($request));
    }

    #[Test]
    public function isAlreadyDiscountedIsTrueForADiscountedGroupsShopper(): void
    {
        $request = $this->requestFor(9);
        $this->basketService->add($request, $this->product->getUid() ?? 0, null, 1);

        self::assertTrue($this->basketService->isAlreadyDiscounted($request));
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
