<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Basket;

use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class BasketServiceTest extends AbstractFrontendTestCase
{
    #[Test]
    public function unitPriceIsSplitIntoNetAndTaxUsingTheRealTaxRate(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BasketServiceTest/basket_service_tax.csv');
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 1, null, 1);
        $item = $basketService->getBasketViewModel($request)->getItems()[0];

        $this->assertSame(10000, $item->getUnitPriceGross()->getCents());
        $this->assertSame(8403, $item->getUnitPriceNet()->getCents());
        $this->assertSame(1597, $item->getLineTotalTax()->getCents());
        $this->assertSame(0.19, $item->getTaxRate());
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
