<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Basket;

use GoldeneZeiten\Products\Core\Domain\Dto\Basket;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketItem;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Basket\BasketStorage;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * A basket line is priced from its article but titled from its product, so an article must belong to the
 * product it is added with - otherwise an expensive product could be bought at a foreign article's price.
 */
final class BasketArticleBindingTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BasketArticleBindingTest/products_with_articles.csv');
    }

    #[Test]
    #[DataProvider('rejectedLineProvider')]
    public function addRejectsAnArticleThatDoesNotBelongToTheProduct(int $productUid, ?int $articleUid): void
    {
        $request = $this->anonymousSessionRequest();

        $this->get(BasketService::class)->add($request, $productUid, $articleUid, 1);

        $this->assertSame([], $this->get(BasketStorage::class)->load($request)->getItems());
    }

    public static function rejectedLineProvider(): \Generator
    {
        yield 'variant product with the article of another product' => [
            'productUid' => 1,
            'articleUid' => 2,
        ];
        yield 'variant product with a non-existent article' => [
            'productUid' => 1,
            'articleUid' => 999,
        ];
        yield 'variant product without an article' => [
            'productUid' => 1,
            'articleUid' => null,
        ];
        yield 'simple product with the article of another product' => [
            'productUid' => 3,
            'articleUid' => 2,
        ];
    }

    #[Test]
    public function addAcceptsAnArticleOfTheProduct(): void
    {
        $request = $this->anonymousSessionRequest();

        $this->get(BasketService::class)->add($request, 1, 1, 1);

        $items = $this->get(BasketStorage::class)->load($request)->getItems();
        $this->assertCount(1, $items);
        $this->assertSame(1, $items[0]->getArticleUid());
    }

    #[Test]
    public function updateCannotIntroduceALineThatWasNeverAdded(): void
    {
        $request = $this->anonymousSessionRequest();

        $this->get(BasketService::class)->update($request, 1, 2, 1);

        $this->assertSame([], $this->get(BasketStorage::class)->load($request)->getItems());
    }

    #[Test]
    public function basketViewDropsAStoredLineWhoseArticleBelongsToAnotherProduct(): void
    {
        $request = $this->anonymousSessionRequest();
        $this->get(BasketStorage::class)->save($request, new Basket([
            new BasketItem(1, 2, 1),
            new BasketItem(3, null, 1),
        ]));

        $basket = $this->get(BasketService::class)->getBasketViewModel($request);

        $this->assertCount(1, $basket->getItems());
        $this->assertSame(3, $basket->getItems()[0]->getProduct()->getUid());
        $this->assertSame(30000, $basket->getTotalGross()->getCents());
    }

    private function anonymousSessionRequest(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser);
    }
}
