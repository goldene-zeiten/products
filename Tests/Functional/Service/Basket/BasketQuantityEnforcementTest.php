<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Basket;

use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Service\Basket\BasketStorage;
use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Regression: basketMinQuantity/basketMaxQuantity were stored but never enforced; also covers the
 * negative-quantity floor.
 */
final class BasketQuantityEnforcementTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BasketQuantityEnforcementTest/basket_service_quantity.csv');
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    #[Test]
    #[DataProvider('addQuantityProvider')]
    public function addEnforcesConfiguredQuantityBounds(int $productUid, ?int $articleUid, int $requestedQuantity, int $expectedQuantity): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, $productUid, $articleUid, $requestedQuantity);

        $this->assertSame($expectedQuantity, $this->get(BasketStorage::class)->load($request)->getItems()[0]->getQuantity());
    }

    public static function addQuantityProvider(): \Generator
    {
        yield 'quantity below product minimum is raised' => ['productUid' => 1, 'articleUid' => null, 'requestedQuantity' => 1, 'expectedQuantity' => 2];
        yield 'quantity above product maximum is lowered' => ['productUid' => 1, 'articleUid' => null, 'requestedQuantity' => 99, 'expectedQuantity' => 5];
        yield 'non-zero article bounds override the product\'s' => ['productUid' => 2, 'articleUid' => 1, 'requestedQuantity' => 99, 'expectedQuantity' => 2];
    }

    #[Test]
    public function negativeQuantityCannotReduceAnExistingLineOnAdd(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();
        $basketService->add($request, 1, null, 3);

        $basketService->add($request, 1, null, -10);

        $this->assertGreaterThanOrEqual(3, $this->get(BasketStorage::class)->load($request)->getItems()[0]->getQuantity());
    }

    #[Test]
    public function updateStillRemovesTheLineOnZeroOrLessRegardlessOfMinimum(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();
        $basketService->add($request, 1, null, 3);

        $basketService->update($request, 1, null, 0);

        $this->assertSame([], $this->get(BasketStorage::class)->load($request)->getItems());
    }

    #[Test]
    public function updateAboveProductMaximumIsLowered(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();
        $basketService->add($request, 1, null, 3);

        $basketService->update($request, 1, null, 99);

        $this->assertSame(5, $this->get(BasketStorage::class)->load($request)->getItems()[0]->getQuantity());
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
