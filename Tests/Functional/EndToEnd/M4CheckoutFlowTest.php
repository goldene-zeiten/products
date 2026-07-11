<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\EndToEnd;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutChoices;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRepository;
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

/**
 * End-to-end coverage of the M4 checkout flow: a shipping method chosen, a free-shipping voucher
 * applied, an alternate delivery address with a gift message, and a "gained" bonus voucher issued
 * afterward for clearing the reward threshold.
 */
final class M4CheckoutFlowTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private OrderPlacementService $orderPlacementService;
    private BasketService $basketService;
    private Product $mainProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/m4_end_to_end.csv');
        // CategoryDiscountPriceProvider still reads Extbase settings eagerly in its constructor,
        // which requires a request resolvable via $GLOBALS['TYPO3_REQUEST'] outside a real dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $this->basketService = $this->get(BasketService::class);
        $this->orderPlacementService = $this->get(OrderPlacementService::class);

        $product = $this->get(ProductRepository::class)->findByUid(1);
        self::assertInstanceOf(Product::class, $product);
        $this->mainProduct = $product;
    }

    #[Test]
    public function shippingIsWaivedByAFreeShippingVoucherAndTheGiftAddressIsSnapshotted(): void
    {
        $request = $this->requestFor(9);
        $this->basketService->add($request, $this->mainProduct->getUid() ?? 0, null, 1);
        $this->basketService->addVoucherCode($request, 'FREESHIP');

        $delivery = new Address(firstName: 'Jane', lastName: 'Doe', street: 'Gift Lane 1', zip: '54321', city: 'Giftville', country: 'DE');
        $choices = new CheckoutChoices(spendPoints: 0, shippingMethodUid: 1, deliveryAddress: $delivery, giftMessage: 'Happy birthday!');

        $order = $this->orderPlacementService->place($request, $this->address(), 'invoice', $choices)->getOrder();

        self::assertSame(1, $order->getShippingMethod());
        self::assertSame(0, $order->getShippingTotal()->getCents(), 'The free-shipping voucher must waive the shipping cost.');
        self::assertSame(['FREESHIP'], $order->getVoucherCodes());

        $deliveryAddress = $order->getDeliveryAddress();
        self::assertNotNull($deliveryAddress);
        self::assertSame('Jane', $deliveryAddress->getFirstName());
        self::assertSame('Happy birthday!', $order->getGiftMessage());

        $this->issueGainedVoucherFor($order, $request);

        $voucher = $this->get(VoucherRepository::class)->findOneByCode($this->gainedVoucherCodeFor($order->getUid() ?? 0));
        self::assertNotNull($voucher, 'A gained voucher must have been issued for clearing the reward threshold.');
        self::assertFalse($voucher->isCombinable());
        self::assertSame(1, $voucher->getUsageLimit());
    }

    private function issueGainedVoucherFor(Order $order, ServerRequestInterface $request): void
    {
        $listener = $this->get(IssueGainedVoucherListener::class);
        $listener(new AfterOrderPlacedEvent($order, $request));
    }

    private function gainedVoucherCodeFor(int $orderUid): string
    {
        $row = $this->getConnectionPool()
            ->getConnectionForTable('tx_products_domain_model_voucher')
            ->select(['code'], 'tx_products_domain_model_voucher', ['generated_from_order' => $orderUid])
            ->fetchAssociative();
        self::assertIsArray($row, 'No gained voucher row was written for this order.');
        return (string)$row['code'];
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
