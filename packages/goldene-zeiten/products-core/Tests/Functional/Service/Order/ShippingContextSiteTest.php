<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\ShippingFallbackFixture\CollectInStoreFixtureProvider;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Carriers word their options in the site's language and read per-site configuration, so the shipping they
 * are asked to quote for an order carries the site of the request.
 */
final class ShippingContextSiteTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-shipping-fallback-fixture',
    ];

    protected function tearDown(): void
    {
        CollectInStoreFixtureProvider::$lastQuotedSite = null;
        parent::tearDown();
    }

    #[Test]
    public function theQuotedShippingOfAnOrderKnowsItsSite(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PaymentDiscountTest/two_rates.csv');
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $site = new Site('products', 1, ['settings' => ['products' => ['shipping' => ['enabled' => true]]]]);
        $request = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('site', $site);
        $basketService = $this->get(BasketService::class);
        $basketService->add($request, 1, null, 1);

        $this->get(OrderCreationService::class)->quote(
            $request,
            $basketService->getBasketViewModel($request, 'DE'),
            new CheckoutSelections('collect:shop'),
            new Address(email: 'buyer@example.com', country: 'DE'),
            $this->get(PaymentMethodRegistry::class)->get('invoice')
        );

        $this->assertSame($site, CollectInStoreFixtureProvider::$lastQuotedSite);
    }
}
