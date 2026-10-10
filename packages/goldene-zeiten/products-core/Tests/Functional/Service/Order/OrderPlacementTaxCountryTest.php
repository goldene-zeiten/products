<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutChoices;
use GoldeneZeiten\Products\Core\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Order\OrderPlacementService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class OrderPlacementTaxCountryTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderPlacementTaxCountryTest/order_placement_tax_country.csv');
    }

    #[Test]
    #[DataProvider('countryDataProvider')]
    public function itemsAreTaxedInTheCountryTheOrderRecordsAsTaxCountry(string $country, float $expectedRate, int $expectedTax): void
    {
        $request = $this->request();
        $this->get(BasketService::class)->add($request, 1, null, 1);

        $order = $this->get(OrderPlacementService::class)->place($request, $this->address($country), 'invoice', new CheckoutChoices(termsAccepted: true))->getOrder();

        $item = $order->getItems()->toArray()[0] ?? null;
        $this->assertInstanceOf(OrderItem::class, $item);
        $this->assertSame($country, $order->getTaxCountry());
        $this->assertSame($expectedRate, $item->getTaxRate());
        $this->assertSame($expectedTax, $order->getTotalTax()->getCents());
    }

    public static function countryDataProvider(): \Generator
    {
        yield 'the default country' => [
            'country' => 'DE',
            'expectedRate' => 0.07,
            'expectedTax' => 65,
        ];
        yield 'a country with its own rate' => [
            'country' => 'FR',
            'expectedRate' => 0.055,
            'expectedTax' => 52,
        ];
    }

    private function request(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $frontendUser->user = ['uid' => 0];
        $site = new Site('products', 1, ['settings' => ['products' => []]]);
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('site', $site);
    }

    private function address(string $country): Address
    {
        return new Address(email: 'buyer@example.com', firstName: 'Jane', lastName: 'Doe', street: 'Main Street 1', zip: '10115', city: 'Paris', country: $country);
    }
}
