<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Basket;

use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
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

    #[Test]
    public function lineNetAndTaxAreDerivedFromTheLineGross(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BasketServiceTest/basket_line_tax.csv');
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 1, null, 100);
        $basket = $basketService->getBasketViewModel($request);
        $item = $basket->getItems()[0];

        $this->assertSame(9900, $item->getLineTotalGross()->getCents());
        $this->assertSame(8319, $item->getLineTotalNet()->getCents());
        $this->assertSame(1581, $item->getLineTotalTax()->getCents());
        $this->assertSame($basket->getTotalGross()->getCents(), $basket->getTotalNet()->getCents() + $basket->getTotalTax()->getCents());
    }

    #[Test]
    public function aRoundedGrossTotalMovesNetAndTaxAlong(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BasketServiceTest/basket_line_tax.csv');
        $basketService = $this->get(BasketService::class);
        $site = new Site('products', 1, ['settings' => ['products' => ['pricing' => ['roundingMode' => 'psychological99']]]]);
        $request = $this->anonymousSessionRequest()->withAttribute('site', $site);

        $basketService->add($request, 1, null, 100);
        $basket = $basketService->getBasketViewModel($request);

        $this->assertSame(9899, $basket->getTotalGross()->getCents());
        $this->assertSame(1581, $basket->getTotalTax()->getCents());
        $this->assertSame(8318, $basket->getTotalNet()->getCents());
    }

    #[Test]
    #[DataProvider('taxCountryDataProvider')]
    public function itemTaxRateIsTheOneOfTheGivenTaxCountry(?string $taxCountry, float $expectedRate, int $expectedLineTax): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BasketServiceTest/basket_line_tax.csv');
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 2, null, 1);
        $item = $basketService->getBasketViewModel($request, $taxCountry)->getItems()[0];

        $this->assertSame($expectedRate, $item->getTaxRate());
        $this->assertSame($expectedLineTax, $item->getLineTotalTax()->getCents());
    }

    public static function taxCountryDataProvider(): \Generator
    {
        yield 'without a tax country the default country applies' => [
            'taxCountry' => null,
            'expectedRate' => 0.07,
            'expectedLineTax' => 65,
        ];
        yield 'an empty tax country is the default country' => [
            'taxCountry' => '',
            'expectedRate' => 0.07,
            'expectedLineTax' => 65,
        ];
        yield 'a tax country with its own rate uses it' => [
            'taxCountry' => 'FR',
            'expectedRate' => 0.055,
            'expectedLineTax' => 52,
        ];
        yield 'a tax country without its own rate falls back to the default country' => [
            'taxCountry' => 'AT',
            'expectedRate' => 0.07,
            'expectedLineTax' => 65,
        ];
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
