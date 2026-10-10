<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Basket;

use GoldeneZeiten\Products\Core\Domain\Dto\Basket;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketItem;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Basket\BasketStorage;
use GoldeneZeiten\Products\EventFixture\DenyingVisibilityChecker;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * A product a visibility checker hides from the visitor must not be buyable by posting its uid.
 */
final class BasketProductVisibilityTest extends AbstractFrontendTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-event-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BasketProductVisibilityTest/products.csv');
        DenyingVisibilityChecker::$enabled = true;
        DenyingVisibilityChecker::$deniedProductUid = 1;
    }

    protected function tearDown(): void
    {
        DenyingVisibilityChecker::$enabled = false;
        DenyingVisibilityChecker::$deniedProductUid = 0;
        parent::tearDown();
    }

    #[Test]
    public function addRejectsAProductHiddenFromTheVisitor(): void
    {
        $request = $this->anonymousSessionRequest();

        $this->get(BasketService::class)->add($request, 1, null, 1);

        $this->assertSame([], $this->get(BasketStorage::class)->load($request)->getItems());
    }

    #[Test]
    public function basketViewDropsAStoredLineOfAProductHiddenFromTheVisitor(): void
    {
        $request = $this->anonymousSessionRequest();
        $this->get(BasketStorage::class)->save($request, new Basket([
            new BasketItem(1, null, 1),
            new BasketItem(2, null, 1),
        ]));

        $basket = $this->get(BasketService::class)->getBasketViewModel($request);

        $this->assertCount(1, $basket->getItems());
        $this->assertSame(2, $basket->getItems()[0]->getProduct()->getUid());
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
