<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Core\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\CashDiscountPaymentMethod;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * A payment method may grant a discount for paying its way - cash, prepayment - and may depend on how the
 * order ships, e.g. cash only when collected in store.
 */
final class PaymentDiscountTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PaymentDiscountTest/two_rates.csv');
    }

    #[Test]
    public function aPaymentDiscountLowersTheVatOfEveryRateItReduces(): void
    {
        $request = $this->request();
        $basketService = $this->get(BasketService::class);
        $basketService->add($request, 1, null, 1);
        $basketService->add($request, 2, null, 1);

        $totals = $this->get(OrderCreationService::class)->quote(
            $request,
            $basketService->getBasketViewModel($request, 'DE'),
            new CheckoutSelections('tablerate:1'),
            new Address(email: 'buyer@example.com', country: 'DE'),
            new CashDiscountPaymentMethod()
        );

        $this->assertSame(19400, $totals->getTotalGross()->getCents());
        // 19 %: 1597 - 48 for its 3.00 share; 7 %: 654 - 20 for its 3.00 share.
        $this->assertSame([19 => 1549, 7 => 634], $totals->getTaxBreakdown());
        $discounts = array_filter(
            $totals->getAdjustments()->all(),
            static fn($adjustment): bool => $adjustment->getType() === AdjustmentType::DISCOUNT
        );
        $this->assertCount(2, $discounts);
    }

    #[Test]
    public function aPaymentMethodLearnsTheChosenShipping(): void
    {
        $request = $this->request();
        $basketService = $this->get(BasketService::class);
        $basketService->add($request, 1, null, 1);
        $paymentMethod = new CashDiscountPaymentMethod();

        $this->get(OrderCreationService::class)->quote(
            $request,
            $basketService->getBasketViewModel($request, 'DE'),
            new CheckoutSelections('tablerate:1'),
            new Address(email: 'buyer@example.com', country: 'DE'),
            $paymentMethod
        );

        $this->assertSame('tablerate:1', $paymentMethod->seenShippingOptionKey);
    }

    private function request(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $site = new Site('products', 1, ['settings' => ['products' => ['shipping' => ['enabled' => true]]]]);
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('site', $site);
    }
}
