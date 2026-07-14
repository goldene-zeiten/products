<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\EndToEnd;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutChoices;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\EventListener\IssueGainedVoucherListener;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Service\Order\OrderPlacementService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class M4CheckoutFlowTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/M4CheckoutFlowTest/m4_end_to_end.csv');
    }

    #[Test]
    public function shippingIsWaivedByAFreeShippingVoucherAndTheGiftAddressIsSnapshotted(): void
    {
        $basketService = $this->get(BasketService::class);
        $orderPlacementService = $this->get(OrderPlacementService::class);
        $mainProduct = $this->mainProduct();
        $request = $this->requestFor(9);
        $basketService->add($request, $mainProduct->getUid() ?? 0, null, 1);
        $basketService->addVoucherCode($request, 'FREESHIP');

        $delivery = new Address(firstName: 'Jane', lastName: 'Doe', street: 'Gift Lane 1', zip: '54321', city: 'Giftville', country: 'DE');
        $choices = new CheckoutChoices(spendPoints: 0, shippingOptionKey: 'tablerate:1', deliveryAddress: $delivery, giftMessage: 'Happy birthday!', termsAccepted: true);

        $order = $orderPlacementService->place($request, $this->address(), 'invoice', $choices)->getOrder();

        $this->assertSame('tablerate', $order->getShippingProvider());
        $this->assertSame('1', $order->getShippingOption());
        $this->assertSame(500, $order->getShippingTotal()->getCents(), 'The free-shipping voucher offset now records the real cost.');
        $this->assertSame(500, $order->getDiscountTotal()->getCents(), 'The voucher creates an equal discount.');
        $this->assertSame(['FREESHIP'], $order->getVoucherCodes());

        $deliveryAddress = $order->getDeliveryAddress();
        $this->assertNotNull($deliveryAddress);
        $this->assertSame('Jane', $deliveryAddress->getFirstName());
        $this->assertSame('Happy birthday!', $order->getGiftMessage());

        $this->issueGainedVoucherFor($order, $request);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/m4_gained_voucher_issued.csv');
    }

    private function mainProduct(): Product
    {
        $product = $this->get(ProductRepository::class)->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);
        return $product;
    }

    private function issueGainedVoucherFor(Order $order, ServerRequestInterface $request): void
    {
        $listener = $this->get(IssueGainedVoucherListener::class);
        $listener(new AfterOrderPlacedEvent($order, $request));
    }

    private function requestFor(int $frontendUserUid): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        if ($frontendUserUid > 0) {
            $frontendUser->user = ['uid' => $frontendUserUid];
        }
        $site = new Site('products', 1, ['settings' => ['products' => [
            'shipping' => ['enabled' => true],
            'vouchers' => ['gained' => [
                'enabled' => true,
                'minimumOrderValue' => '1.00',
                'rewardType' => 'fixed',
                'rewardValue' => '5.00',
            ]],
        ]]]);
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('site', $site);
    }

    private function address(): Address
    {
        return new Address(email: 'buyer@example.com', country: 'DE');
    }
}
