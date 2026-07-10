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

/**
 * Regression coverage for a real (fixed) bug: TaxService::getTaxRate() used to return the whole
 * percentage stored on TaxRate (19.00) instead of a fraction (0.19), which BasketService's
 * "1 + rate" arithmetic silently treated as already-a-fraction - producing a net price of ~5% of
 * gross instead of ~84% for a 19% rate. Masked until a separate TaxRateRepository storage-page bug
 * was fixed (it had always returned null/0.0 before, i.e. every order silently reported 0% tax).
 */
final class BasketServiceTest extends AbstractFrontendTestCase
{
    #[Test]
    public function unitPriceIsSplitIntoNetAndTaxUsingTheRealTaxRate(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/basket_service_tax.csv');
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 1, null, 1);
        $item = $basketService->getBasketViewModel($request)->getItems()[0];

        self::assertSame(10000, $item->getUnitPriceGross()->getCents());
        self::assertSame(8403, $item->getUnitPriceNet()->getCents());
        self::assertSame(1597, $item->getLineTotalTax()->getCents());
        self::assertSame(0.19, $item->getTaxRate());
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
